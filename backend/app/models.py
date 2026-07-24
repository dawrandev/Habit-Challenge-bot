"""Ma'lumotlar modeli — SPEC.md §3. DB nomlari inglizcha."""

from datetime import date, datetime
from enum import Enum
from typing import Optional

from sqlmodel import JSON, Column, Field, SQLModel


class BattleStatus(str, Enum):
    pending = "pending"      # taklif yuborilgan, qabul kutilyapti
    active = "active"
    finished = "finished"
    cancelled = "cancelled"  # o'zaro bekor
    forfeit = "forfeit"      # bir tomon tashlab ketdi


class Cadence(str, Enum):
    daily = "daily"
    weekly_days = "weekly_days"  # muayyan hafta kunlari


class CompletionStatus(str, Enum):
    pending = "pending"            # yuborilgan, tekshiruv kutilyapti
    approved = "approved"          # inson tasdiqladi
    auto_approved = "auto_approved"  # 24s deadline o'tdi
    rejected = "rejected"          # rad etilgan
    missed = "missed"              # umuman yuborilmagan (kun yopilganda)


class DisputeStatus(str, Enum):
    open = "open"
    resolved_approved = "resolved_approved"  # tekshiruvchi fikrini o'zgartirdi
    resolved_upheld = "resolved_upheld"      # "bahsli rad" — qaror kuchida


class User(SQLModel, table=True):
    id: Optional[int] = Field(default=None, primary_key=True)
    telegram_id: int = Field(index=True, unique=True)
    username: Optional[str] = None
    first_name: str = ""
    photo_url: Optional[str] = None
    language: str = "uz"
    created_at: datetime = Field(default_factory=datetime.utcnow)


class Battle(SQLModel, table=True):
    id: Optional[int] = Field(default=None, primary_key=True)
    title: str
    status: BattleStatus = BattleStatus.pending
    period_days: int = 7
    start_date: date
    end_date: date
    timezone: str = "Asia/Tashkent"
    created_by: int = Field(foreign_key="user.id")
    winner_id: Optional[int] = Field(default=None, foreign_key="user.id")
    invite_token: str = Field(index=True)
    created_at: datetime = Field(default_factory=datetime.utcnow)


class BattleParticipant(SQLModel, table=True):
    """1v1 hozir, lekin pivot — guruh uchun ochiq (SPEC §3)."""

    id: Optional[int] = Field(default=None, primary_key=True)
    battle_id: int = Field(foreign_key="battle.id", index=True)
    user_id: int = Field(foreign_key="user.id", index=True)
    accepted: bool = False
    score: float = 0.0  # scoring engine qayta hisoblaydi


class Challenge(SQLModel, table=True):
    id: Optional[int] = Field(default=None, primary_key=True)
    battle_id: int = Field(foreign_key="battle.id", index=True)
    template_key: Optional[str] = None  # 'read' | 'sport' | ... yoki None (erkin)
    name: str = ""                        # erkin nom yoki shablon nomi
    icon: str = "🎯"
    cadence: Cadence = Cadence.daily
    weekdays: list[int] = Field(default_factory=list, sa_column=Column(JSON))  # 0=Du..6=Ya
    start_date: date                      # har challenge o'z boshlanishi (SPEC §3)
    active: bool = True
    created_at: datetime = Field(default_factory=datetime.utcnow)


class Completion(SQLModel, table=True):
    """Bir kun, bir challenge, bir user uchun hisobot."""

    id: Optional[int] = Field(default=None, primary_key=True)
    challenge_id: int = Field(foreign_key="challenge.id", index=True)
    user_id: int = Field(foreign_key="user.id", index=True)
    day: date = Field(index=True)
    status: CompletionStatus = CompletionStatus.pending
    file_id: Optional[str] = None  # Telegram file_id (rasm o'zi Telegram'da)
    submitted_at: Optional[datetime] = None
    resolved_at: Optional[datetime] = None


class Verification(SQLModel, table=True):
    """Har tekshiruv qarori log qilinadi (audit trail — SPEC §5)."""

    id: Optional[int] = Field(default=None, primary_key=True)
    completion_id: int = Field(foreign_key="completion.id", index=True)
    verifier_id: int = Field(foreign_key="user.id")
    approve: bool
    is_dispute_review: bool = False
    created_at: datetime = Field(default_factory=datetime.utcnow)


class Dispute(SQLModel, table=True):
    id: Optional[int] = Field(default=None, primary_key=True)
    completion_id: int = Field(foreign_key="completion.id", index=True)
    opened_by: int = Field(foreign_key="user.id")
    status: DisputeStatus = DisputeStatus.open
    created_at: datetime = Field(default_factory=datetime.utcnow)
    resolved_at: Optional[datetime] = None


class ChatMessage(SQLModel, table=True):
    """Chat = matn + voqealar tasmasi (SPEC §8)."""

    id: Optional[int] = Field(default=None, primary_key=True)
    battle_id: int = Field(foreign_key="battle.id", index=True)
    sender_id: Optional[int] = Field(default=None, foreign_key="user.id")  # None=tizim
    kind: str = "text"  # 'text' | 'event'
    text: str = ""
    created_at: datetime = Field(default_factory=datetime.utcnow)
