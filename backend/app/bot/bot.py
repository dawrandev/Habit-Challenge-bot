"""aiogram bot — polling (SPEC.md §1). Rasm Telegram'da saqlanadi (§10)."""

import asyncio
import logging
import os
import ssl

from aiogram import Bot, Dispatcher
from aiogram.client.default import DefaultBotProperties
from aiogram.filters import CommandObject, CommandStart
from aiogram.types import (
    BufferedInputFile,
    InlineKeyboardButton,
    InlineKeyboardMarkup,
    Message,
    WebAppInfo,
)

from ..config import settings

log = logging.getLogger("bot")

_bot: Bot | None = None
dp = Dispatcher()


def _bot_ssl_context() -> ssl.SSLContext:
    """Bot uchun SSL konteksti.

    aiogram default holda certifi ishlatadi, lekin ba'zi serverlarda tarmoq maxsus
    root bilan TLS'ni ushlaydi (curl=tizim CA ishlaydi, certifi=ishlamaydi). Shuning
    uchun TIZIM CA bundle'ini ishlatamiz. BOT_SSL_INSECURE=1 bo'lsa — tekshiruvsiz.
    """
    if os.environ.get("BOT_SSL_INSECURE") == "1":
        ctx = ssl.create_default_context()
        ctx.check_hostname = False
        ctx.verify_mode = ssl.CERT_NONE
        return ctx
    for path in (
        "/etc/ssl/certs/ca-certificates.crt",  # Debian/Ubuntu (curl shu bilan ishlaydi)
        "/etc/pki/tls/certs/ca-bundle.crt",    # RHEL/CentOS
        "/etc/ssl/cert.pem",                   # Alpine/macOS
    ):
        if os.path.isfile(path):
            return ssl.create_default_context(cafile=path)
    return ssl.create_default_context()


def get_bot() -> Bot:
    global _bot
    if _bot is None:
        from aiogram.client.session.aiohttp import AiohttpSession

        # aiogram default certifi'ni tizim CA (yoki tekshiruvsiz) bilan almashtiramiz
        session = AiohttpSession()
        session._connector_init["ssl"] = _bot_ssl_context()
        _bot = Bot(
            settings.bot_token,
            session=session,
            default=DefaultBotProperties(parse_mode="HTML"),
        )
    return _bot


def _webapp_kb(start_param: str | None = None) -> InlineKeyboardMarkup:
    url = settings.webapp_url
    if start_param:
        url = f"{url}?tgWebAppStartParam={start_param}"
    return InlineKeyboardMarkup(
        inline_keyboard=[
            [InlineKeyboardButton(text="⚔️ Ochish", web_app=WebAppInfo(url=url))]
        ]
    )


@dp.message(CommandStart(deep_link=True))
async def start_deeplink(message: Message, command: CommandObject) -> None:
    # Deep link: t.me/Bot?start=battle_<token> → Mini App'ga uzatiladi
    await message.answer(
        "Senga battle taklifi bor! Ochib qabul qil 👇",
        reply_markup=_webapp_kb(command.args),
    )


@dp.message(CommandStart())
async def start(message: Message) -> None:
    await message.answer(
        "<b>Battle</b> — odat dueli.\nDo'stingni chaqir, har kuni isbot yubor, "
        "bir-biringni tekshir, g'olib bo'l! 🔥",
        reply_markup=_webapp_kb(),
    )


# --------------------------------------------------------------------------
# Rasm: saqlash (file_id olish) va proxy (ko'rsatish)
# --------------------------------------------------------------------------
async def store_photo(data: bytes, filename: str = "proof.jpg") -> str:
    """Rasmni maxsus kanalga yuboradi, file_id qaytaradi (SPEC §10)."""
    bot = get_bot()
    msg = await bot.send_photo(
        settings.storage_chat_id, BufferedInputFile(data, filename)
    )
    return msg.photo[-1].file_id


async def fetch_photo(file_id: str) -> bytes:
    """file_id bo'yicha rasmni Telegram'dan yuklaydi (proxy uchun)."""
    bot = get_bot()
    tg_file = await bot.get_file(file_id)
    buf = await bot.download_file(tg_file.file_path)
    return buf.read()


async def send_message(chat_id: int, text: str) -> None:
    """Notifikatsiya (SPEC §7)."""
    try:
        await get_bot().send_message(chat_id, text)
    except Exception as e:  # noqa: BLE001
        log.warning("send_message failed: %s", e)


async def run_polling() -> None:
    bot = get_bot()
    # Webhook o'chirish + eski updatelarni tashlash (konflikt oldini oladi)
    try:
        await bot.delete_webhook(drop_pending_updates=True)
    except Exception as e:  # noqa: BLE001
        log.warning("delete_webhook: %s", e)

    # Chidamli polling: xatolikda qayta urinadi, shutdown'da to'xtaydi
    while True:
        try:
            log.info("Bot polling boshlandi")
            await dp.start_polling(bot, handle_signals=False)
            log.info("Polling normal tugadi")
            break
        except asyncio.CancelledError:
            log.info("Polling to'xtatildi (shutdown)")
            break
        except Exception as e:  # noqa: BLE001
            log.error("Polling xatosi: %r — 5s dan keyin qayta urinaman", e)
            await asyncio.sleep(5)
