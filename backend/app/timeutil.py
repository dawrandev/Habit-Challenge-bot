"""Vaqt yordamchilari — kun oxiri battle timezone bo'yicha (SPEC §10, default Asia/Tashkent)."""

from datetime import datetime, timedelta

from .config import settings


def now_local() -> datetime:
    try:
        from zoneinfo import ZoneInfo

        return datetime.now(ZoneInfo(settings.timezone))
    except Exception:
        # tzdata bo'lmasa — Toshkent UTC+5 (DST yo'q)
        return datetime.utcnow() + timedelta(hours=5)


def today_local():
    return now_local().date()
