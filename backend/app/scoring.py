"""Scoring engine — SPEC.md §4. Aniq qoidalar:

  - Tasdiqlangan bajarish (approved/auto_approved): +points_per_completion (1.0)
  - O'tkazib yuborilgan/rad etilgan navbatdagi kun (kun tugagan): -penalty_per_miss (0.5)
  - Bugungi hali hal bo'lmagan kun: 0 (jarima yo'q, kun tugamagan)
  - Umumiy hisob score_floor (0) dan past bo'lmaydi
  - G'olib = barcha challenge ballari UMUMIY yig'indisi
"""

from dataclasses import dataclass, field
from datetime import date, timedelta

from .config import settings


def is_due(cadence: str, weekdays: list[int], day: date) -> bool:
    """Shu kuni challenge navbatdami?"""
    if cadence == "daily":
        return True
    return day.weekday() in weekdays  # 0=Dushanba .. 6=Yakshanba


def due_days(cadence: str, weekdays: list[int], start: date, until: date):
    d = start
    while d <= until:
        if is_due(cadence, weekdays, d):
            yield d
        d += timedelta(days=1)


@dataclass
class ChallengeScore:
    cadence: str
    weekdays: list[int]
    start_date: date
    approved: set[date] = field(default_factory=set)  # tasdiqlangan kunlar (shu user)


def participant_score(
    challenges: list[ChallengeScore],
    today: date,
    end_date: date,
    *,
    points: float | None = None,
    penalty: float | None = None,
    floor: float | None = None,
) -> float:
    """Bitta ishtirokchining battle bo'yicha UMUMIY balli."""
    points = settings.points_per_completion if points is None else points
    penalty = settings.penalty_per_miss if penalty is None else penalty
    floor = settings.score_floor if floor is None else floor

    last_day = min(today, end_date)
    total = 0.0
    for ch in challenges:
        for d in due_days(ch.cadence, ch.weekdays, ch.start_date, last_day):
            if d in ch.approved:
                total += points
            elif d < today:
                # kun tugagan, tasdiqlangan bajarish yo'q → jarima
                total -= penalty
            # d == today va tasdiqlanmagan → 0 (kun tugamagan)
    return max(floor, total)


def challenge_breakdown(
    ch: ChallengeScore, today: date, end_date: date, *, points: float | None = None
) -> int:
    """UI uchun: shu challenge bo'yicha tasdiqlangan kunlar soni (ball emas)."""
    last_day = min(today, end_date)
    return sum(1 for d in due_days(ch.cadence, ch.weekdays, ch.start_date, last_day) if d in ch.approved)


def decide_winner(
    a_score: float, a_completions: int, b_score: float, b_completions: int
) -> int:
    """Tiebreaker (SPEC §4): ball → ko'proq bajargan → durang(0).

    Qaytaradi: 1 (A yutdi), -1 (B yutdi), 0 (durang).
    """
    if a_score != b_score:
        return 1 if a_score > b_score else -1
    if a_completions != b_completions:
        return 1 if a_completions > b_completions else -1
    return 0


# ------------------------------------------------------------------
# O'z-o'zini tekshiruv:  python -m app.scoring
# ------------------------------------------------------------------
if __name__ == "__main__":
    D = date
    today = D(2026, 7, 10)
    end = D(2026, 7, 31)

    # Har kunlik challenge, 5-kundan boshlangan (5,6,7,8,9 — 5 tugagan kun; 10 = bugun)
    ch = ChallengeScore(
        cadence="daily",
        weekdays=[],
        start_date=D(2026, 7, 5),
        approved={D(2026, 7, 5), D(2026, 7, 6), D(2026, 7, 8)},  # 3 tasdiq
    )
    # Tugagan kunlar: 5,6,7,8,9 (5 ta). Tasdiq: 5,6,8 → +3. Miss: 7,9 → -1.0. Bugun 10: 0.
    s = participant_score([ch], today, end, points=1.0, penalty=0.5, floor=0.0)
    assert s == 3 * 1.0 - 2 * 0.5, f"kutilgan 2.0, chiqdi {s}"

    # Floor: hammasi miss (tasdiq yo'q)
    ch2 = ChallengeScore("daily", [], D(2026, 7, 5), approved=set())
    s2 = participant_score([ch2], today, end, points=1.0, penalty=0.5, floor=0.0)
    assert s2 == 0.0, f"floor 0 kutilgan, chiqdi {s2}"  # 5 miss = -2.5 → floor 0

    # Weekly (Du/Cho/Ju = 0,2,4). 2026-07: 6(Du),8(Cho),10(Ju)...
    ch3 = ChallengeScore("weekly_days", [0, 2, 4], D(2026, 7, 6), approved={D(2026, 7, 6)})
    # Navbat kunlari <=10: 6,8,10. Bugun 10. Tugaganlar: 6(tasdiq +1), 8(miss -0.5). = 0.5
    s3 = participant_score([ch3], today, end, points=1.0, penalty=0.5, floor=0.0)
    assert s3 == 0.5, f"kutilgan 0.5, chiqdi {s3}"

    # Tiebreaker
    assert decide_winner(10, 12, 10, 9) == 1
    assert decide_winner(8, 5, 10, 5) == -1
    assert decide_winner(7, 7, 7, 7) == 0

    print("✓ scoring testlari o'tdi")
