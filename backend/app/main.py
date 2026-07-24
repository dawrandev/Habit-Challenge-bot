"""FastAPI + aiogram (bitta xizmat). Lifespan: DB + scheduler + bot polling."""

import asyncio
import logging
import os
from contextlib import asynccontextmanager

from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import FileResponse, Response

from .api.routes import router
from .config import settings
from .db import init_db

logging.basicConfig(level=logging.INFO)
log = logging.getLogger("app")


@asynccontextmanager
async def lifespan(app: FastAPI):
    init_db()

    scheduler = None
    try:
        from .scheduler import start_scheduler

        scheduler = start_scheduler()
    except Exception as e:  # noqa: BLE001
        log.warning("Scheduler o'chirilgan: %s", e)

    bot_task = None
    if settings.bot_token:
        from .bot.bot import run_polling

        bot_task = asyncio.create_task(run_polling())
    else:
        log.warning("BOT_TOKEN yo'q — bot ishga tushmadi (dev rejim)")

    yield

    if bot_task:
        bot_task.cancel()
    if scheduler:
        scheduler.shutdown(wait=False)


app = FastAPI(title="Battle Bot API", lifespan=lifespan)

origins = (
    ["*"]
    if settings.cors_origins == "*"
    else [o.strip() for o in settings.cors_origins.split(",")]
)
app.add_middleware(
    CORSMiddleware,
    allow_origins=origins,
    allow_credentials=False,  # header-based auth, cookie yo'q
    allow_methods=["*"],
    allow_headers=["*"],
)
app.include_router(router, prefix="/api")


@app.get("/health")
def health():
    return {"ok": True}


@app.get("/api/photo/{file_id}")
async def photo(file_id: str):
    """Rasm proxy (SPEC §10) — bot token oshkor bo'lmaydi."""
    if not settings.bot_token:
        return Response(status_code=404)
    from .bot.bot import fetch_photo

    data = await fetch_photo(file_id)
    return Response(
        content=data,
        media_type="image/jpeg",
        headers={"Cache-Control": "private, max-age=3600"},
    )


# --------------------------------------------------------------------------
# Frontend (SPA) — bitta xizmatdan beriladi (deploy). FRONTEND_DIST bo'lsa.
# --------------------------------------------------------------------------
_DIST = os.environ.get(
    "FRONTEND_DIST",
    os.path.join(os.path.dirname(__file__), "..", "..", "frontend", "dist"),
)

if os.path.isdir(_DIST):

    @app.get("/{full_path:path}")
    async def spa(full_path: str):
        candidate = os.path.join(_DIST, full_path)
        if full_path and os.path.isfile(candidate):
            return FileResponse(candidate)  # assets (js/css/font)
        return FileResponse(os.path.join(_DIST, "index.html"))  # SPA fallback

