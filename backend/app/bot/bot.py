"""aiogram bot — polling (SPEC.md §1). Rasm Telegram'da saqlanadi (§10)."""

import logging

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


def get_bot() -> Bot:
    global _bot
    if _bot is None:
        _bot = Bot(
            settings.bot_token,
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
    log.info("Bot polling boshlandi")
    await dp.start_polling(get_bot())
