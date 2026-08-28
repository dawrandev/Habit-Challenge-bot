<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deploy diagnostikasi — "nega ishlamayapti?" savoliga bir buyruqda javob.
 *
 * Taklif havolalari jimgina buzilishi mumkin bo'lgan joylarni tekshiradi:
 * bot tokeni, username, webhook, Mini App URL, rasm kanali, cron.
 */
class DoctorCommand extends Command
{
    protected $signature = 'battle:doctor';

    protected $description = 'Deploy sozlamalarini tekshiradi (bot, webhook, URL, cron)';

    private int $problems = 0;

    public function handle(TelegramClient $client): int
    {
        $this->newLine();
        $this->line('  <options=bold>🩺 Battle Bot — deploy tekshiruvi</>');
        $this->newLine();

        $this->checkEnvironment();
        $botUsername = $this->checkBot($client);
        $this->checkWebhook($client);
        $this->checkWebappUrl();
        $this->checkStorageChannel($client);
        $this->checkDatabase();
        $this->checkSchedule();
        $this->checkInviteLink($botUsername);

        $this->newLine();
        if ($this->problems === 0) {
            $this->line('  <fg=green;options=bold>✅ Hammasi joyida.</>');
        } else {
            $this->line("  <fg=red;options=bold>❌ {$this->problems} ta muammo topildi (yuqoriga qara).</>");
        }
        $this->newLine();

        return $this->problems === 0 ? self::SUCCESS : self::FAILURE;
    }

    private function ok(string $label, string $detail = ''): void
    {
        $this->line("  <fg=green>✓</> {$label}".($detail !== '' ? " <fg=gray>{$detail}</>" : ''));
    }

    private function bad(string $label, string $fix): void
    {
        $this->problems++;
        $this->line("  <fg=red>✗</> {$label}");
        $this->line("      <fg=yellow>→ {$fix}</>");
    }

    private function soft(string $label, string $note): void
    {
        $this->line("  <fg=yellow>!</> {$label}");
        $this->line("      <fg=gray>{$note}</>");
    }

    private function checkEnvironment(): void
    {
        $env = (string) config('app.env');
        $debug = (bool) config('app.debug');
        $devAuth = (bool) config('telegram.allow_dev_auth');

        if ($env === 'production') {
            $this->ok('APP_ENV', 'production');
            if ($debug) {
                $this->bad('APP_DEBUG production`da yoqilgan', 'APP_DEBUG=false qo\'y va `php artisan config:cache`');
            }
            if ($devAuth) {
                $this->bad('TELEGRAM_ALLOW_DEV_AUTH production`da yoqilgan', 'TELEGRAM_ALLOW_DEV_AUTH=false qo\'y');
            }
        } else {
            $this->soft("APP_ENV = {$env}", 'Serverda production bo\'lishi kerak');
        }
    }

    private function checkBot(TelegramClient $client): ?string
    {
        if (! $client->enabled()) {
            $this->bad('TELEGRAM_BOT_TOKEN yo\'q', '.env ga BotFather bergan tokenni qo\'y');

            return null;
        }

        $me = $client->getMe();
        if (! ($me['ok'] ?? false)) {
            $this->bad('Bot tokeni ishlamadi', 'Token noto\'g\'ri yoki internetga chiqib bo\'lmadi');

            return null;
        }

        $real = (string) ($me['result']['username'] ?? '');
        $this->ok('Bot tokeni', "@{$real}");

        $configured = trim((string) config('telegram.bot_username'), '@ ');
        if ($configured === '') {
            $this->bad(
                'TELEGRAM_BOT_USERNAME bo\'sh',
                "Taklif havolalari yasalmaydi. .env ga qo'y: TELEGRAM_BOT_USERNAME={$real}",
            );
        } elseif (strcasecmp($configured, $real) !== 0) {
            $this->bad(
                "TELEGRAM_BOT_USERNAME mos emas (@{$configured} ≠ @{$real})",
                "Taklif havolalari boshqa botga ketadi. .env: TELEGRAM_BOT_USERNAME={$real}",
            );
        } else {
            $this->ok('Bot username', "@{$configured}");
        }

        return $real;
    }

    private function checkWebhook(TelegramClient $client): void
    {
        if (! $client->enabled()) {
            return;
        }

        $info = $client->getWebhookInfo();
        $url = (string) ($info['url'] ?? '');

        if ($url === '') {
            $this->bad(
                'Webhook o\'rnatilmagan',
                '/start va taklif havolalari ISHLAMAYDI. Bajar: php artisan battle:set-webhook',
            );

            return;
        }

        $expected = rtrim((string) config('telegram.webapp_url'), '/').'/api/telegram/webhook';
        if ($url !== $expected) {
            $this->soft("Webhook: {$url}", "Kutilgani: {$expected}");
        } else {
            $this->ok('Webhook', $url);
        }

        if (($info['last_error_message'] ?? null)) {
            $this->bad(
                'Telegram webhook`ga ulana olmayapti: '.$info['last_error_message'],
                'HTTPS sertifikat va domen ochiqligini tekshir',
            );
        }

        $secret = (string) config('telegram.webhook_secret');
        if ($secret === '') {
            $this->soft('TELEGRAM_WEBHOOK_SECRET bo\'sh', 'Webhook`ni har kim chaqira oladi — tasodifiy satr qo\'ygan ma\'qul');
        }
    }

    private function checkWebappUrl(): void
    {
        $url = (string) config('telegram.webapp_url');

        if ($url === '' || str_contains($url, 'localhost') || str_contains($url, 'your-domain')) {
            $this->bad(
                "TELEGRAM_WEBAPP_URL to'g'ri emas: {$url}",
                'Mini App ochilmaydi. .env ga real HTTPS manzilni qo\'y',
            );

            return;
        }

        if (! str_starts_with($url, 'https://')) {
            $this->bad("TELEGRAM_WEBAPP_URL HTTPS emas: {$url}", 'Telegram Mini App faqat HTTPS`da ochiladi');

            return;
        }

        $this->ok('Mini App URL', $url);
    }

    private function checkStorageChannel(TelegramClient $client): void
    {
        $chat = (string) config('telegram.storage_chat_id');

        if ($chat === '') {
            $this->bad(
                'TELEGRAM_STORAGE_CHAT_ID yo\'q',
                'Isbot rasmlari saqlanmaydi. Private kanal yarat, botni admin qil, ID`sini qo\'y',
            );

            return;
        }

        $this->ok('Rasm kanali', $chat);
    }

    private function checkDatabase(): void
    {
        try {
            DB::connection()->getPdo();
        } catch (\Throwable $e) {
            $this->bad('DB`ga ulanib bo\'lmadi: '.$e->getMessage(), '.env dagi DB_* qiymatlarini tekshir');

            return;
        }

        $pending = collect(DB::table('migrations')->pluck('migration'));
        $files = collect(glob(database_path('migrations/*.php')))
            ->map(fn ($f) => basename($f, '.php'));
        $missing = $files->diff($pending);

        if ($missing->isNotEmpty()) {
            $this->bad(
                "Bajarilmagan migratsiya: {$missing->count()} ta",
                'Bajar: php artisan migrate --force',
            );

            return;
        }

        $this->ok('DB va migratsiyalar', config('database.default'));
    }

    private function checkSchedule(): void
    {
        // Kunlik yopish cron`siz ishlamaydi — ballar va missiya natijasi shunga bog'liq.
        $this->soft(
            'Cron`ni qo\'lda tasdiqla',
            'crontab -l | grep schedule:run — bo\'lmasa ballar va missiya natijasi hisoblanmaydi',
        );
    }

    private function checkInviteLink(?string $botUsername): void
    {
        if ($botUsername === null) {
            return;
        }

        $this->newLine();
        $this->line('  <fg=gray>Taklif havolasi shunday ko\'rinadi:</>');
        $this->line("  <fg=cyan>https://t.me/{$botUsername}?start=quest_XXXXXXXX</>");
    }
}
