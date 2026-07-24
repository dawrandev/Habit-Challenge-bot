"""FastAPI endpointlar. Auth = Telegram initData (X-Telegram-Init-Data header)."""

import secrets
from datetime import datetime, timedelta

from fastapi import (
    APIRouter,
    Depends,
    File,
    Form,
    Header,
    HTTPException,
    UploadFile,
)
from pydantic import BaseModel
from sqlmodel import Session, select

from ..auth import validate_init_data
from ..config import settings
from ..db import get_session
from ..models import (
    Battle,
    BattleParticipant,
    BattleStatus,
    Cadence,
    Challenge,
    ChatMessage,
    Completion,
    CompletionStatus,
    Dispute,
    DisputeStatus,
    User,
    Verification,
)
from ..scoring import (
    ChallengeScore,
    challenge_breakdown,
    is_due,
    participant_score,
)
from ..timeutil import today_local

router = APIRouter()


# --------------------------------------------------------------------------
# Auth dependency
# --------------------------------------------------------------------------
def get_current_user(
    x_telegram_init_data: str | None = Header(default=None),
    x_dev_telegram_id: str | None = Header(default=None),
    session: Session = Depends(get_session),
) -> User:
    tg_user: dict | None = None

    data = validate_init_data(x_telegram_init_data or "")
    if data:
        tg_user = data["user"]
    elif (settings.allow_dev_auth or not settings.bot_token) and x_dev_telegram_id:
        # DEV: lokal brauzer testi (token bo'lsa ham, ALLOW_DEV_AUTH=1 bo'lganda)
        tg_user = {"id": int(x_dev_telegram_id), "first_name": "Dev"}

    if not tg_user or "id" not in tg_user:
        raise HTTPException(status_code=401, detail="Invalid initData")

    user = session.exec(
        select(User).where(User.telegram_id == tg_user["id"])
    ).first()
    if not user:
        user = User(telegram_id=tg_user["id"])
    user.username = tg_user.get("username") or user.username
    user.first_name = tg_user.get("first_name") or user.first_name or "?"
    user.photo_url = tg_user.get("photo_url") or user.photo_url
    if tg_user.get("language_code") in {"uz", "en", "ru", "tr"}:
        user.language = tg_user["language_code"]
    session.add(user)
    session.commit()
    session.refresh(user)
    return user


# --------------------------------------------------------------------------
# Schemas
# --------------------------------------------------------------------------
class ChallengeIn(BaseModel):
    template_key: str | None = None
    name: str = ""
    icon: str = "🎯"
    cadence: Cadence = Cadence.daily
    weekdays: list[int] = []


class BattleCreate(BaseModel):
    title: str
    period_days: int = 7
    start_tomorrow: bool = True
    challenges: list[ChallengeIn]


# --------------------------------------------------------------------------
# Helpers
# --------------------------------------------------------------------------
def _score_for(session: Session, battle: Battle, user_id: int):
    """Bir ishtirokchining umumiy balli + challenge taqsimoti."""
    from ..models import Completion, CompletionStatus

    challenges = session.exec(
        select(Challenge).where(Challenge.battle_id == battle.id)
    ).all()
    scores: list[ChallengeScore] = []
    breakdown = {}
    for ch in challenges:
        approved = {
            c.day
            for c in session.exec(
                select(Completion).where(
                    Completion.challenge_id == ch.id,
                    Completion.user_id == user_id,
                    Completion.status.in_(
                        [CompletionStatus.approved, CompletionStatus.auto_approved]
                    ),
                )
            ).all()
        }
        cs = ChallengeScore(ch.cadence, ch.weekdays, ch.start_date, approved)
        scores.append(cs)
        breakdown[ch.id] = challenge_breakdown(cs, today_local(), battle.end_date)
    total = participant_score(scores, today_local(), battle.end_date)
    return total, breakdown


# --------------------------------------------------------------------------
# Endpoints
# --------------------------------------------------------------------------
@router.get("/me")
def me(user: User = Depends(get_current_user)):
    return user


def _time_left(battle: Battle) -> dict:
    from datetime import datetime as _dt

    from ..timeutil import now_local

    now = now_local()
    # davr end_date kunining oxiri (24:00)
    end = _dt.combine(battle.end_date + timedelta(days=1), _dt.min.time())
    if now.tzinfo:
        end = end.replace(tzinfo=now.tzinfo)
    delta = end - now
    secs = max(int(delta.total_seconds()), 0)
    return {"days_left": secs // 86400, "hours_left": (secs % 86400) // 3600}


@router.get("/battles")
def list_battles(
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    parts = session.exec(
        select(BattleParticipant).where(BattleParticipant.user_id == user.id)
    ).all()
    out = []
    for p in parts:
        battle = session.get(Battle, p.battle_id)
        if not battle:
            continue
        all_parts = session.exec(
            select(BattleParticipant).where(
                BattleParticipant.battle_id == battle.id
            )
        ).all()
        players = []
        for pp in all_parts:
            u = session.get(User, pp.user_id)
            total, _ = _score_for(session, battle, pp.user_id)
            players.append(
                {"user": u, "score": total, "is_me": pp.user_id == user.id}
            )
        out.append({"battle": battle, "players": players, **_time_left(battle)})
    return out


@router.post("/battles")
def create_battle(
    payload: BattleCreate,
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    start = today_local() + (timedelta(days=1) if payload.start_tomorrow else timedelta(0))
    end = start + timedelta(days=payload.period_days)
    battle = Battle(
        title=payload.title,
        period_days=payload.period_days,
        start_date=start,
        end_date=end,
        timezone=settings.timezone,
        created_by=user.id,
        invite_token=secrets.token_urlsafe(9),
    )
    session.add(battle)
    session.commit()
    session.refresh(battle)

    session.add(BattleParticipant(battle_id=battle.id, user_id=user.id, accepted=True))
    for c in payload.challenges:
        session.add(
            Challenge(
                battle_id=battle.id,
                template_key=c.template_key,
                name=c.name,
                icon=c.icon,
                cadence=c.cadence,
                weekdays=c.weekdays,
                start_date=start,
            )
        )
    session.commit()
    return {"battle": battle, "invite_token": battle.invite_token}


@router.post("/battles/{token}/accept")
def accept_battle(
    token: str,
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    battle = session.exec(
        select(Battle).where(Battle.invite_token == token)
    ).first()
    if not battle:
        raise HTTPException(404, "Battle topilmadi")
    existing = session.exec(
        select(BattleParticipant).where(
            BattleParticipant.battle_id == battle.id,
            BattleParticipant.user_id == user.id,
        )
    ).first()
    if not existing:
        session.add(
            BattleParticipant(battle_id=battle.id, user_id=user.id, accepted=True)
        )
    battle.status = BattleStatus.active
    session.add(battle)
    session.commit()
    return {"ok": True, "battle_id": battle.id}


@router.get("/battles/{battle_id}")
def battle_detail(
    battle_id: int,
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    battle = session.get(Battle, battle_id)
    if not battle:
        raise HTTPException(404, "Battle topilmadi")
    parts = session.exec(
        select(BattleParticipant).where(BattleParticipant.battle_id == battle_id)
    ).all()
    challenges = session.exec(
        select(Challenge).where(Challenge.battle_id == battle_id)
    ).all()

    players = []
    for p in parts:
        u = session.get(User, p.user_id)
        total, breakdown = _score_for(session, battle, p.user_id)
        players.append(
            {
                "user": u,
                "score": total,
                "breakdown": breakdown,
                "is_me": p.user_id == user.id,
            }
        )
    return {"battle": battle, "players": players, "challenges": challenges}


# --------------------------------------------------------------------------
# Notifikatsiya yordamchilari (SPEC §7)
# --------------------------------------------------------------------------
async def _notify(telegram_ids: list[int], text: str) -> None:
    if not settings.bot_token:
        return
    from ..bot.bot import send_message

    for tid in telegram_ids:
        await send_message(tid, text)


def _others_telegram_ids(
    session: Session, battle_id: int, exclude_user_id: int
) -> list[int]:
    parts = session.exec(
        select(BattleParticipant).where(
            BattleParticipant.battle_id == battle_id,
            BattleParticipant.user_id != exclude_user_id,
        )
    ).all()
    ids = []
    for p in parts:
        u = session.get(User, p.user_id)
        if u:
            ids.append(u.telegram_id)
    return ids


def _is_participant(session: Session, battle_id: int, user_id: int) -> bool:
    return (
        session.exec(
            select(BattleParticipant).where(
                BattleParticipant.battle_id == battle_id,
                BattleParticipant.user_id == user_id,
            )
        ).first()
        is not None
    )


# --------------------------------------------------------------------------
# Hisobot yuborish (jonli kamera → rasm → file_id) — SPEC §5, §10
# --------------------------------------------------------------------------
@router.post("/completions")
async def submit_completion(
    challenge_id: int = Form(...),
    file: UploadFile = File(...),
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    ch = session.get(Challenge, challenge_id)
    if not ch:
        raise HTTPException(404, "Challenge topilmadi")
    if not _is_participant(session, ch.battle_id, user.id):
        raise HTTPException(403, "Sen bu battle ishtirokchisi emassan")

    day = today_local()
    data = await file.read()
    if settings.bot_token and settings.storage_chat_id:
        from ..bot.bot import store_photo

        file_id = await store_photo(data, f"proof_{user.id}_{day}.jpg")
    else:
        file_id = f"dev-{user.id}-{challenge_id}-{day}"  # DEV placeholder

    comp = session.exec(
        select(Completion).where(
            Completion.challenge_id == challenge_id,
            Completion.user_id == user.id,
            Completion.day == day,
        )
    ).first()
    if not comp:
        comp = Completion(challenge_id=challenge_id, user_id=user.id, day=day)
    comp.file_id = file_id
    comp.status = CompletionStatus.pending  # rad etilgan bo'lsa ham qayta yuborish
    comp.submitted_at = datetime.utcnow()
    comp.resolved_at = None
    session.add(comp)
    session.commit()
    session.refresh(comp)

    await _notify(
        _others_telegram_ids(session, ch.battle_id, user.id),
        f"📸 {user.first_name} yangi hisobot yubordi — tekshir!",
    )
    return comp


# --------------------------------------------------------------------------
# Tekshiruv (tasdiq / rad) — SPEC §5
# --------------------------------------------------------------------------
class VerifyIn(BaseModel):
    approve: bool


@router.post("/completions/{completion_id}/verify")
async def verify_completion(
    completion_id: int,
    payload: VerifyIn,
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    comp = session.get(Completion, completion_id)
    if not comp:
        raise HTTPException(404, "Hisobot topilmadi")
    if comp.user_id == user.id:
        raise HTTPException(403, "O'z hisobotingni tekshira olmaysan")
    ch = session.get(Challenge, comp.challenge_id)
    if not ch or not _is_participant(session, ch.battle_id, user.id):
        raise HTTPException(403, "Ruxsat yo'q")
    if comp.status != CompletionStatus.pending:
        raise HTTPException(409, "Bu hisobot allaqachon hal qilingan")

    # ochiq nizo bormi?
    dispute = session.exec(
        select(Dispute).where(
            Dispute.completion_id == comp.id, Dispute.status == DisputeStatus.open
        )
    ).first()

    session.add(
        Verification(
            completion_id=comp.id,
            verifier_id=user.id,
            approve=payload.approve,
            is_dispute_review=dispute is not None,
        )
    )
    comp.status = (
        CompletionStatus.approved if payload.approve else CompletionStatus.rejected
    )
    comp.resolved_at = datetime.utcnow()
    session.add(comp)

    if dispute:
        dispute.status = (
            DisputeStatus.resolved_approved
            if payload.approve
            else DisputeStatus.resolved_upheld
        )
        dispute.resolved_at = datetime.utcnow()
        session.add(dispute)

    session.commit()

    verb = "tasdiqladi ✓" if payload.approve else "rad etdi ✕"
    await _notify(
        [session.get(User, comp.user_id).telegram_id],
        f"{user.first_name} hisobotingni {verb}",
    )
    return {"ok": True, "status": comp.status}


# --------------------------------------------------------------------------
# Nizo — SPEC §5, §12–13
# --------------------------------------------------------------------------
@router.post("/completions/{completion_id}/dispute")
async def open_dispute(
    completion_id: int,
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    comp = session.get(Completion, completion_id)
    if not comp:
        raise HTTPException(404, "Hisobot topilmadi")
    if comp.user_id != user.id:
        raise HTTPException(403, "Faqat o'z hisobotingga nizo ocha olasan")
    if comp.status != CompletionStatus.rejected:
        raise HTTPException(409, "Faqat rad etilgan hisobotга nizo")

    ch = session.get(Challenge, comp.challenge_id)
    session.add(Dispute(completion_id=comp.id, opened_by=user.id))
    # qayta ko'rish uchun pending'ga qaytariladi
    comp.status = CompletionStatus.pending
    comp.resolved_at = None
    session.add(comp)
    session.commit()

    await _notify(
        _others_telegram_ids(session, ch.battle_id, user.id),
        f"⚑ {user.first_name} qaroringga nizo ochdi — qayta ko'rib chiq",
    )
    return {"ok": True}


# --------------------------------------------------------------------------
# Bugun kutilayotган (battle detali uchun) + tekshiruv navbati (Faoliyat)
# --------------------------------------------------------------------------
@router.get("/battles/{battle_id}/today")
def today_tasks(
    battle_id: int,
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    day = today_local()
    challenges = session.exec(
        select(Challenge).where(
            Challenge.battle_id == battle_id, Challenge.active == True  # noqa: E712
        )
    ).all()
    out = []
    for ch in challenges:
        if ch.start_date > day or not is_due(ch.cadence, ch.weekdays, day):
            continue
        comp = session.exec(
            select(Completion).where(
                Completion.challenge_id == ch.id,
                Completion.user_id == user.id,
                Completion.day == day,
            )
        ).first()
        out.append(
            {"challenge": ch, "status": comp.status if comp else None}
        )
    return out


@router.get("/verify-queue")
def verify_queue(
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    """Boshqa ishtirokchilarning tekshiruv kutayotган pending hisobotlari."""
    my_battles = [
        p.battle_id
        for p in session.exec(
            select(BattleParticipant).where(BattleParticipant.user_id == user.id)
        ).all()
    ]
    out = []
    for bid in my_battles:
        challenges = session.exec(
            select(Challenge).where(Challenge.battle_id == bid)
        ).all()
        for ch in challenges:
            pend = session.exec(
                select(Completion).where(
                    Completion.challenge_id == ch.id,
                    Completion.user_id != user.id,
                    Completion.status == CompletionStatus.pending,
                )
            ).all()
            for c in pend:
                rival = session.get(User, c.user_id)
                out.append({"completion": c, "challenge": ch, "rival": rival})
    return out


# --------------------------------------------------------------------------
# Chat (matn + voqealar) — SPEC §8
# --------------------------------------------------------------------------
class MessageIn(BaseModel):
    text: str


@router.get("/battles/{battle_id}/messages")
def get_messages(
    battle_id: int,
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    if not _is_participant(session, battle_id, user.id):
        raise HTTPException(403, "Ruxsat yo'q")
    msgs = session.exec(
        select(ChatMessage)
        .where(ChatMessage.battle_id == battle_id)
        .order_by(ChatMessage.created_at)
    ).all()
    return msgs


@router.post("/battles/{battle_id}/messages")
async def post_message(
    battle_id: int,
    payload: MessageIn,
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    if not _is_participant(session, battle_id, user.id):
        raise HTTPException(403, "Ruxsat yo'q")
    text = payload.text.strip()
    if not text:
        raise HTTPException(400, "Bo'sh xabar")
    msg = ChatMessage(battle_id=battle_id, sender_id=user.id, text=text[:2000])
    session.add(msg)
    session.commit()
    session.refresh(msg)
    await _notify(
        _others_telegram_ids(session, battle_id, user.id),
        f"💬 {user.first_name}: {text[:80]}",
    )
    return msg


# --------------------------------------------------------------------------
# DEV: demo ma'lumot (faqat bot token yo'q bo'lganda)
# --------------------------------------------------------------------------
@router.post("/dev/seed")
def dev_seed(
    user: User = Depends(get_current_user),
    session: Session = Depends(get_session),
):
    if settings.bot_token:
        raise HTTPException(403, "Seed faqat dev rejimda")

    rival = session.exec(select(User).where(User.telegram_id == 999)).first()
    if not rival:
        rival = User(telegram_id=999, first_name="Ali", username="ali")
        session.add(rival)
        session.commit()
        session.refresh(rival)

    today = today_local()
    start = today - timedelta(days=6)
    battle = Battle(
        title="Iyul dueli",
        status=BattleStatus.active,
        period_days=7,
        start_date=start,
        end_date=today + timedelta(days=1),
        timezone=settings.timezone,
        created_by=user.id,
        invite_token=secrets.token_urlsafe(9),
    )
    session.add(battle)
    session.commit()
    session.refresh(battle)
    session.add(BattleParticipant(battle_id=battle.id, user_id=user.id, accepted=True))
    session.add(BattleParticipant(battle_id=battle.id, user_id=rival.id, accepted=True))

    chdefs = [
        ("read", "📖", Cadence.daily, []),
        ("sport", "🏃", Cadence.daily, []),
        ("earlyRise", "🌅", Cadence.weekly_days, [0, 2, 4]),
    ]
    challenges = []
    for key, icon, cad, wd in chdefs:
        ch = Challenge(
            battle_id=battle.id,
            template_key=key,
            icon=icon,
            cadence=cad,
            weekdays=wd,
            start_date=start,
        )
        session.add(ch)
        session.commit()
        session.refresh(ch)
        challenges.append(ch)

    def approve(ch: Challenge, uid: int, offsets: list[int]):
        for off in offsets:
            d = start + timedelta(days=off)
            if d > today or not is_due(ch.cadence, ch.weekdays, d):
                continue
            session.add(
                Completion(
                    challenge_id=ch.id,
                    user_id=uid,
                    day=d,
                    status=CompletionStatus.approved,
                    submitted_at=datetime.utcnow(),
                    resolved_at=datetime.utcnow(),
                    file_id="dev-seed",
                )
            )

    approve(challenges[0], user.id, [0, 1, 2, 3, 4, 5, 6])
    approve(challenges[0], rival.id, [0, 1, 2, 4, 6])
    approve(challenges[1], user.id, [0, 1, 3, 4, 5])
    approve(challenges[1], rival.id, [0, 2, 4])
    approve(challenges[2], user.id, [0, 2, 4])
    approve(challenges[2], rival.id, [0, 2, 4])
    session.commit()
    return {"battle_id": battle.id, "title": battle.title}
