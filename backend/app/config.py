from pydantic_settings import BaseSettings, SettingsConfigDict


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
