import os
import ssl

from pydantic_settings import BaseSettings, SettingsConfigDict

# SSL: Python default/certifi tizimga qo'shilgan maxsus root'ni topa olmaydi
# ("self-signed certificate"), garchi curl ishlasa ham. Shuning uchun aynan TIZIM
# CA bazasini ishlatamiz (curl aynan shuni ishlatadi). Bu barcha aiohttp/aiogram
# ulanishlariga ta'sir qiladi.
_SYSTEM_CA_CANDIDATES = [
    "/etc/ssl/certs/ca-certificates.crt",  # Debian/Ubuntu
    "/etc/pki/tls/certs/ca-bundle.crt",    # RHEL/CentOS
    "/etc/ssl/cert.pem",                   # Alpine/macOS
]
_CA = next((p for p in _SYSTEM_CA_CANDIDATES if os.path.isfile(p)), None)
if _CA:
    try:
        os.environ.setdefault("SSL_CERT_FILE", _CA)
        _orig_create_ctx = ssl.create_default_context

        def _system_create_ctx(*args, **kwargs):
            if not (kwargs.get("cafile") or kwargs.get("capath") or kwargs.get("cadata")):
                kwargs["cafile"] = _CA
            return _orig_create_ctx(*args, **kwargs)

        ssl.create_default_context = _system_create_ctx
    except Exception:  # noqa: BLE001
        pass


class Settings(BaseSettings):
    """Muhit sozlamalari (.env dan). SPEC.md §1, §4."""

    model_config = SettingsConfigDict(env_file=".env", extra="ignore")

    # Telegram
    bot_token: str = ""
    storage_chat_id: int = 0  # rasm saqlanadigan kanal
    allow_dev_auth: bool = False  # lokal brauzer testi (prod'da False)

    # DB
    database_url: str = "sqlite:///./battlebot.db"

    # Mini App
    webapp_url: str = "http://localhost:5173"
    cors_origins: str = "*"

    # Vaqt (kun oxiri)
    timezone: str = "Asia/Tashkent"

    # Scoring
    points_per_completion: float = 1.0
    penalty_per_miss: float = 0.5
    score_floor: float = 0.0
    verify_deadline_hours: int = 24


settings = Settings()
