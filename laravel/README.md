# Battle Bot — Laravel ⚔️

Odat-**duel** Telegram Mini App. Backend **Laravel** (PHP), frontend **React** (o'zgarmagan,
`public/`'da). To'liq spetsifikatsiya: `../SPEC.md`.

> **Nega Laravel:** FastPanel (PHP-panel)da PHP sayti webroot'ga ega → **Let's Encrypt SSL avtomatik
> ishlaydi** (Python reverse-proxy'dagi HTTP-01 muammosi yo'q). Bitta xizmat ham API'ni, ham Mini
> App'ni beradi.

## Arxitektura (clean architecture, OOP)

```
app/
├── Domain/Scoring/          # sof biznes-logika (framework'siz)
│   ├── ScoringEngine.php    # SPEC §4 qoidalari (+1 / −0.5 / floor)
│   ├── ChallengeScore.php   # value object
│   └── WinnerResolver.php   # tiebreaker
├── Enums/                   # BattleStatus, Cadence, CompletionStatus, DisputeStatus
├── Models/                  # Eloquent (User, Battle, Challenge, Completion, ...)
├── Services/                # application qatlami
│   ├── ScoringService, BattleService, CompletionService,
│   ├── VerificationService, DisputeService, ChatService, BattleClosingService
│   └── Telegram/            # TelegramClient, InitDataValidator, PhotoService, NotificationService
├── Http/
│   ├── Middleware/AuthenticateTelegram.php   # initData auth (SPEC §2)
│   ├── Controllers/Api/     # thin controllerlar
│   └── Requests/            # FormRequest validatsiya
├── Console/Commands/        # CloseDay, AutoApprove, SetWebhook
└── Support/Clock.php        # timezone (Asia/Tashkent)
```

**Qatlamlar:** Controller → Service → (Domain / Eloquent). Scoring domeni to'liq sof va testlanadi.

## Lokal ishga tushirish

```bash
composer install
cp .env.example .env && php artisan key:generate
# .env: DB (sqlite lokal uchun tayyor), TELEGRAM_ALLOW_DEV_AUTH=true
php artisan migrate
php artisan serve --port 8010
```
- Bot token yo'q bo'lsa — API `X-Dev-Telegram-Id` header bilan sinaladi.
- Frontend (React) `public/`'da tayyor (`../frontend`'dan build qilinadi).

## Deploy (FastPanel — PHP sayti)

1. **PHP sayti** yarat → document root = `laravel/public`
2. Loyihani yukla (git clone / FTP) → `composer install --no-dev --optimize-autoloader`
3. `.env`: `APP_KEY` (key:generate), **MySQL** (FastPanel DB), `TELEGRAM_BOT_TOKEN`,
   `TELEGRAM_STORAGE_CHAT_ID`, `TELEGRAM_WEBAPP_URL=https://domening`, `ALLOW_DEV_AUTH=false`
4. `php artisan migrate --force && php artisan config:cache`
5. **Frontend build** → `frontend/dist/*` ni `public/`'ga ko'chir
6. **SSL:** FastPanel'da PHP sayti uchun Let's Encrypt — **avtomatik ishlaydi** ✅
7. **Webhook:** `php artisan battle:set-webhook`
8. **BotFather** → Mini App URL = `https://domening`
9. **Cron** (kunlik yopish): FastPanel Scheduler'ga `php artisan schedule:run` har daqiqa

## API (Mini App uchun)

`GET /api/me` · `GET|POST /api/battles` · `POST /api/battles/{token}/accept` ·
`GET /api/battles/{id}` · `/today` · `/messages` · `POST /api/completions` ·
`/verify` · `/dispute` · `GET /api/verify-queue` · `GET /api/photo/{fileId}` ·
`POST /api/telegram/webhook`

Auth: `X-Telegram-Init-Data` header (imzo tekshiruvi), dev: `X-Dev-Telegram-Id`.
