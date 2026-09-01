# Battle Bot ⚔️🎯

Odatni **boshqa odam oldida javobgarlik** bilan shakllantiruvchi Telegram Mini App.
Har kuni **jonli kamera** bilan isbot yuboriladi va uni **inson tekshiradi**.

Ikki rejim bor:

| | ⚔️ **Duel** | 🎯 **Missiya** |
|---|---|---|
| Munosabat | 2 raqib, **simmetrik** | 1 **bajaruvchi** + 1 **guvoh**, asimmetrik |
| Odat kimniki | ikkalasiniki | **faqat bajaruvchiniki** |
| Kim tekshiradi | bir-birini | **faqat guvoh** |
| Natija | ballar bo'yicha g'olib | **maqsad foiziga** yetildimi |
| Qachon kerak | bir xil odatni birga tashlaganda | odat faqat **senga** kerak bo'lganda |

> Missiya nega bor: bir odamga kerak bo'lgan odat ikkinchisiga kerak bo'lmasligi mumkin.
> Unda do'st raqib emas — **guvoh** bo'ladi: o'zi bajarmaydi, faqat isbotingni tekshiradi.

To'liq spetsifikatsiya: [`SPEC.md`](./SPEC.md).

## Texnologiya

| Qatlam | Tanlov |
|--------|--------|
| Frontend | React 19 + Vite + TypeScript + Tailwind v4 + motion + i18next (Telegram Mini App) |
| Backend + Bot | **PHP: Laravel 12** (API + Telegram webhook, bitta xizmat) |
| DB | MySQL (prod) / SQLite (dev) |
| Rasm | Telegram serveri (`file_id`) + backend proxy |
| Chartlar | Inline SVG (kutubxonasiz) — halqa, tracker heatmap, trend, barlar |
| Dizayn | "Arena" — dual-rang tug-of-war, olov animatsiyalari |
| Tillar | 🇺🇿 🇬🇧 🇷🇺 🇹🇷 |

> ℹ️ `backend/` papkasi — **eski Python (FastAPI+aiogram) prototipi**, 2026-07-25 dan beri
> ishlatilmaydi. Faol backend — `laravel/`.

## Loyiha tuzilmasi

```
Habit-Challenge-bot/
├── SPEC.md              # yagona manba spetsifikatsiya
├── frontend/            # React Mini App (build → laravel/public)
│   └── src/
│       ├── pages/       # Home, Battle, Quest, Activity, Chat, Profile, ProofCapture
│       ├── components/
│       │   └── charts/  # ProgressRing, TrackerGrid, StreakChain, TrendChart, ChallengeBars
│       ├── i18n/        # 4 til locale
│       └── lib/         # api.ts (duel) · quests.ts (missiya)
├── laravel/             # FAOL backend
│   └── app/
│       ├── Contracts/ProofContext.php     # duel/missiya umumiy isbot kontrakti
│       ├── Domain/Scoring/                # duel ball dvigateli (sof)
│       ├── Domain/Quest/                  # missiya statistika dvigateli (sof)
│       ├── Models/                        # Battle, Quest, Challenge, Completion...
│       ├── Services/                      # BattleService, QuestService, Verification...
│       └── Http/Controllers/Api/
└── backend/             # ⚠️ eski Python prototipi (ishlatilmaydi)
```


## Ishga tushirish (dev)

### Backend (Laravel)
```bash
cd laravel
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate            # DB_CONNECTION=sqlite bo'lsa — fayl o'zi yaratiladi
php artisan serve --port=8000
```

- `TELEGRAM_BOT_TOKEN` bo'sh bo'lsa — dev rejim: API `X-Dev-Telegram-Id: <id>` header bilan sinaladi.
- Testlar: `php artisan test` · Kod uslubi: `./vendor/bin/pint`

### Frontend
```bash
cd frontend
npm install
npm run dev        # http://localhost:5173 — /api → :8000 ga proxy
```

Telegram tashqarisida (oddiy brauzerda) frontend `X-Dev-Telegram-Id: 111` yuboradi,
shuning uchun ilova to'g'ridan-to'g'ri ochiladi.

## Deploy

Ilova **bitta xizmat**: Laravel ham API'ni (`/api/*`), ham frontend SPA'ni (`public/index.html`)
beradi. Bitta HTTPS origin — Telegram Mini App uchun ideal, CORS muammosi yo'q.

### 1. Telegram tayyorlash (BotFather)
1. [@BotFather](https://t.me/BotFather) → `/newbot` → **TELEGRAM_BOT_TOKEN** ol.
2. Rasm saqlash uchun **private kanal** yarat, botni **admin** qil, **TELEGRAM_STORAGE_CHAT_ID** ol
   (masalan `-1001234567890`).
3. Deploy'dan keyin: `php artisan battle:set-webhook` va `php artisan battle:set-menu`.

### 2. Frontend'ni build qilish (lokalda, deploy'dan oldin)
```bash
cd frontend
npm run build
cp -r dist/assets/* ../laravel/public/assets/
cp dist/index.html ../laravel/public/index.html
```
Build natijasi repo'ga commit qilinadi — serverda `npm` kerak emas.

> Bot username build'ga **qotirilmaydi** — u `/api/config` orqali `.env` dan
> ish vaqtida olinadi. Ya'ni botni almashtirsangiz rebuild shart emas.
> (Ilgari `VITE_BOT_USERNAME` build flagi bo'lgan; uni unutish barcha taklif
> havolalarini jimgina `t.me/YourBot` ga yuborardi.)

### 3. Serverda

`laravel/` papkasi ichida:
```bash
bash deploy.sh
```
Repo ildizida bo'lsangiz — `bash laravel/deploy.sh`. Skript o'zi turgan
papkaga o'tadi, shuning uchun qayerdan chaqirsangiz ham ishlaydi.

Nima qiladi: `git pull` · `composer install` · `migrate --force` · kesh ·
webhook + menyu tugmasi · oxirida `battle:doctor` tekshiruvi.

`deploy.sh` **`migrate --force`** ishlatadi (`migrate:fresh` EMAS) — mavjud ma'lumot saqlanadi.
Oxirida `battle:doctor` sozlamalarni tekshiradi (bot, webhook, URL, DB) va muammoni
aniq ko'rsatadi. Xohlagan vaqtda alohida ham chaqirsa bo'ladi:

```bash
php artisan battle:doctor
```

**Kerakli env:**
```
APP_ENV=production
APP_DEBUG=false
DB_CONNECTION=mysql            # DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD
TELEGRAM_BOT_TOKEN=...
TELEGRAM_BOT_USERNAME=YourBot   # taklif havolalari shu nom bilan yasaladi
TELEGRAM_STORAGE_CHAT_ID=-100...
TELEGRAM_WEBHOOK_SECRET=<tasodifiy satr>
TELEGRAM_WEBAPP_URL=https://sizning-domeningiz
TELEGRAM_ALLOW_DEV_AUTH=false  # ⚠️ PROD'DA ALBATTA false
BATTLE_TIMEZONE=Asia/Tashkent
```

### 4. Cron (kunlik yopish shart)
Ballar, o'tkazib yuborilgan kunlar va missiya natijasi shu yerda hisoblanadi:
```
* * * * * cd /path/to/laravel && php artisan schedule:run >> /dev/null 2>&1
```
- `battle:close-day` — har kuni 00:05 (Toshkent): duel g'olibi + missiya natijasi
- `battle:auto-approve` — har soatda: 24s tekshirilmagan isbotlarni avtomatik tasdiqlash

## Holat

**Tayyor:** ⚔️ Duel (ball, tug-of-war, g'olib) · 🎯 Missiya (guvoh, maqsad foizi, 5 chart) ·
jonli kamera isbot · inson tekshiruvi + 24s auto-tasdiq · nizo · chat · deep-link taklif ·
4 til · Arena dizayn.

**Keyingi:** eslatmalar (SPEC §7 — 20:00/22:00, tinch soatlar) · duel natija ekrani + arxiv +
rematch · quit=forfeit · bot xabarlarining i18n'i (hozir qattiq kodlangan o'zbekcha).
