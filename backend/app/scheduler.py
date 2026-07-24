"""Kunlik yopish + auto-tasdiq (SPEC.md §4, §5, §11).

- auto_approve_overdue: 24s tekshirilmagan hisobot → avtomatik tasdiq
- mark_missed: navbatdagi kun yuborilmagan → missed (jarima scoring'da)
- finish_due_battles: davr tugagan battle → g'olib + arxiv
"""

import logging
from datetime import datetime, timedelta

from sqlmodel import Session, select

from .config import settings
from .db import engine
from .models import (
    Battle,
    BattleParticipant,
    BattleStatus,
    Challenge,
    Completion,
    CompletionStatus,
)
from .scoring import ChallengeScore, decide_winner, is_due, participant_score
from .timeutil import now_local, today_local

log = logging.getLogger("scheduler")


def auto_approve_overdue(session: Session) -> int:
    """Deadline o'tgan pending hisobotlarni avtomatik tasdiqlaydi."""
    cutoff = datetime.utcnow() - timedelta(hours=settings.verify_deadline_hours)
    pend = session.exec(
        select(Completion).where(
            Completion.status == CompletionStatus.pending,
            Completion.submitted_at < cutoff,
        )
    ).all()
    for c in pend:
        c.status = CompletionStatus.auto_approved
        c.resolved_at = datetime.utcnow()
        session.add(c)
    if pend:
        session.commit()
    return len(pend)


def mark_missed(session: Session) -> int:
    """Kechagi va oldingi navbatdagi kunlarda hisobot bo'lmasa → missed."""
    today = today_local()
    count = 0
    battles = session.exec(
        select(Battle).where(Battle.status == BattleStatus.active)
    ).all()
    for battle in battles:
        parts = session.exec(
            select(BattleParticipant).where(
                BattleParticipant.battle_id == battle.id
            )
        ).all()
        challenges = session.exec(
            select(Challenge).where(Challenge.battle_id == battle.id)
        ).all()
        for ch in challenges:
            day = ch.start_date
            last = min(today - timedelta(days=1), battle.end_date)
            while day <= last:
                if is_due(ch.cadence, ch.weekdays, day):
                    for p in parts:
                        exists = session.exec(
                            select(Completion).where(
                                Completion.challenge_id == ch.id,
                                Completion.user_id == p.user_id,
                                Completion.day == day,
                            )
                        ).first()
                        if not exists:
                            session.add(
                                Completion(
                                    challenge_id=ch.id,
                                    user_id=p.user_id,
                                    day=day,
                                    status=CompletionStatus.missed,
                                )
                            )
                            count += 1
                day += timedelta(days=1)
    if count:
        session.commit()
    return count


def recompute_scores(session: Session) -> None:
    today = today_local()
    battles = session.exec(
        select(Battle).where(Battle.status == BattleStatus.active)
    ).all()
    for battle in battles:
        parts = session.exec(
            select(BattleParticipant).where(
                BattleParticipant.battle_id == battle.id
            )
        ).all()
        challenges = session.exec(
            select(Challenge).where(Challenge.battle_id == battle.id)
        ).all()
        for p in parts:
            scores = []
            for ch in challenges:
                approved = {
                    c.day
                    for c in session.exec(
                        select(Completion).where(
                            Completion.challenge_id == ch.id,
                            Completion.user_id == p.user_id,
                            Completion.status.in_(
                                [
                                    CompletionStatus.approved,
                                    CompletionStatus.auto_approved,
                                ]
                            ),
                        )
                    ).all()
                }
                scores.append(
                    ChallengeScore(ch.cadence, ch.weekdays, ch.start_date, approved)
                )
            p.score = participant_score(scores, today, battle.end_date)
            session.add(p)
    session.commit()


def finish_due_battles(session: Session) -> int:
    """Davr tugagan battle'larni yakunlaydi, g'olibni aniqlaydi."""
    today = today_local()
    count = 0
    battles = session.exec(
        select(Battle).where(
            Battle.status == BattleStatus.active, Battle.end_date < today
        )
    ).all()
    for battle in battles:
        parts = session.exec(
            select(BattleParticipant).where(
                BattleParticipant.battle_id == battle.id
            )
        ).all()
        if len(parts) == 2:
            a, b = parts
            a_comp = session.exec(
                select(Completion).where(
                    Completion.user_id == a.user_id,
                    Completion.status.in_(
                        [CompletionStatus.approved, CompletionStatus.auto_approved]
                    ),
                )
            ).all()
            b_comp = session.exec(
                select(Completion).where(
                    Completion.user_id == b.user_id,
                    Completion.status.in_(
                        [CompletionStatus.approved, CompletionStatus.auto_approved]
                    ),
                )
            ).all()
            result = decide_winner(a.score, len(a_comp), b.score, len(b_comp))
            if result > 0:
                battle.winner_id = a.user_id
            elif result < 0:
                battle.winner_id = b.user_id
        battle.status = BattleStatus.finished
        session.add(battle)
        count += 1
    if count:
        session.commit()
    return count


def run_daily_close() -> None:
    log.info("Kunlik yopish: %s", now_local().isoformat())
    with Session(engine) as session:
        mark_missed(session)
        recompute_scores(session)
        finish_due_battles(session)


def run_hourly() -> None:
    with Session(engine) as session:
        n = auto_approve_overdue(session)
        if n:
            recompute_scores(session)


def start_scheduler():
    from apscheduler.schedulers.asyncio import AsyncIOScheduler
    from apscheduler.triggers.cron import CronTrigger

    scheduler = AsyncIOScheduler(timezone=settings.timezone)
    # Har kun 00:05 (mahalliy) — kunni yopish
    scheduler.add_job(run_daily_close, CronTrigger(hour=0, minute=5))
    # Har soatda — deadline o'tgan tekshiruvlarni auto-tasdiq
    scheduler.add_job(run_hourly, CronTrigger(minute=0))
    scheduler.start()
    log.info("Scheduler ishga tushdi")
    return scheduler
