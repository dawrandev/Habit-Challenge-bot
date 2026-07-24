"""Telegram Mini App initData imzo tekshiruvi (SPEC.md §2).

https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
"""

import hashlib
import hmac
import json
from urllib.parse import parse_qsl

from .config import settings


def validate_init_data(init_data: str) -> dict | None:
    """initData imzosini bot token bilan tekshiradi.

    Qaytaradi: {"user": {...}, "auth_date": "..."} yoki None (yaroqsiz).
    """
    if not init_data or not settings.bot_token:
        return None
    try:
        parsed = dict(parse_qsl(init_data, strict_parsing=True))
    except ValueError:
        return None

    received_hash = parsed.pop("hash", None)
    if not received_hash:
        return None

    data_check_string = "\n".join(
        f"{k}={v}" for k, v in sorted(parsed.items())
    )
    secret_key = hmac.new(
        b"WebAppData", settings.bot_token.encode(), hashlib.sha256
    ).digest()
    calc_hash = hmac.new(
        secret_key, data_check_string.encode(), hashlib.sha256
    ).hexdigest()

    if not hmac.compare_digest(calc_hash, received_hash):
        return None

    user = {}
    if "user" in parsed:
        try:
            user = json.loads(parsed["user"])
        except json.JSONDecodeError:
            user = {}

    return {"user": user, "auth_date": parsed.get("auth_date"), "raw": parsed}
