# Battle Bot ⚔️

Odatlarni **bellashuv** tarzida shakllantiruvchi Telegram Mini App. Do'stlar bir-biriga
odat-challenge yuboradi, har kuni **jonli kamera** bilan isbot yuboradi, **bir-birini tekshiradi**,
davr oxirida yig'ilgan ballardan g'olib chiqadi.

To'liq spetsifikatsiya: [`SPEC.md`](./SPEC.md).

## Texnologiya

| Qatlam | Tanlov |
|--------|--------|
| Frontend | React + Vite + TypeScript + Tailwind v4 + motion + i18next (Telegram Mini App) |
| Backend + Bot | Python: FastAPI + aiogram (bitta xizmat, polling) |
| DB | PostgreSQL (prod) / SQLite (dev) |
| Rasm | Telegram serveri (`file_id`) + backend proxy |
| Dizayn | "Arena" — dual-rang tug-of-war, olov animatsiyalari |
| Tillar | 🇺🇿 🇬🇧 🇷🇺 🇹🇷 |

## Loyiha tuzilmasi

```
battle bot/
├── SPEC.md              # yagona manba spetsifikatsiya
├── frontend/            # React Mini App
│   └── src/
│       ├── pages/       # Home, Battle, Activity, Chat, Profile, ProofCapture
│       ├── components/  # BottomNav, VerifyModal, PageTransition
│       ├── i18n/        # 4 til locale
│       └── lib/         # demo data (keyin API)
└── backend/             # FastAPI + aiogram
    └── app/
        ├── models.py    # SQLModel (users, battles, challenges, completions...)
        ├── scoring.py   # scoring engine (+ o'z-o'zini test)
        ├── scheduler.py # kunlik yopish + auto-tasdiq
        ├── auth.py      # Telegram initData verify
        ├── api/routes.py
        └── bot/bot.py   # /start, deep-link, rasm saqlash/proxy
```

## Ishga tushirish (dev)

### Frontend
```bash
cd frontend
npm install
npm run dev        # http://localhost:5173
```

### Backend
```bash
cd backend
python -m venv venv
venv\Scripts\activate            # Windows (yoki: source venv/bin/activate)
pip install -r requirements.txt
copy .env.example .env           # va BOT_TOKEN, STORAGE_CHAT_ID to'ldir
python -m uvicorn app.main:app --reload --port 8000
```

- `BOT_TOKEN` bo'lmasa — bot ishga tushmaydi (dev rejim), API `X-Dev-Telegram-Id` header bilan sinaladi.
- Scoring testi: `python -m app.scoring`

## Deploy (SPEC §1)

Ilova **bitta xizmat** sifatida deploy qilinadi: FastAPI ham API'ni, ham frontend (SPA)'ni beradi
(`FRONTEND_DIST` orqali). Bu Telegram Mini App uchun ideal — bitta HTTPS origin, CORS muammosi yo'q.

### 1. Telegram tayyorlash (BotFather)
1. [@BotFather](https://t.me/BotFather) → `/newbot` → **BOT_TOKEN** ol.
2. Rasm saqlash uchun **maxsus kanal** yarat (private), botni **admin** qil, **STORAGE_CHAT_ID** ol
   (kanal ID, masalan `-1001234567890`).
3. Deploy'dan keyin BotFather → `/newapp` (yoki bot sozlamalari) → **Mini App URL** = deploy HTTPS URL.

### 2. Docker bilan (bir buyruq)
```bash
docker build --build-arg VITE_BOT_USERNAME=YourBot -t battlebot .
docker run -p 8000:8000 --env-file backend/.env battlebot
```

### 3. Railway / Render (tavsiya)
- Repo'ni ula → Docker aniqlanadi (root `Dockerfile`).
- **Env:** `BOT_TOKEN`, `STORAGE_CHAT_ID`, `DATABASE_URL` (Postgres — Neon/Railway),
  `WEBAPP_URL` (deploy URL), `TIMEZONE=Asia/Tashkent`.
- **Build arg:** `VITE_BOT_USERNAME` (taklif havolasi uchun).
- Bepul HTTPS subdomen beriladi → shu URL'ni BotFather'ga Mini App sifatida qo'y.

Bot **polling**'da ishlaydi (webhook/domain shart emas), lekin **Mini App uchun HTTPS URL shart**.

## Holat

**Tayyor:** Arena dizayn + 5 sahifa + i18n + jonli kamera + animatsiyalar; backend modellar,
scoring (testlar o'tgan), auth, bot skelet, scheduler, rasm proxy.

**Keyingi:** completion/verify/dispute/chat endpointlari · frontend↔backend ulanish (React Query) ·
real bot token + storage kanal + deploy.
