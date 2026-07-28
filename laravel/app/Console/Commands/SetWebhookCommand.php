<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;

class SetWebhookCommand extends Command
{
    protected $signature = 'battle:set-webhook {url? : To\'liq webhook URL (default: WEBAPP_URL/api/telegram/webhook)}';

    protected $description = 'Telegram bot webhook manzilini o\'rnatadi (SPEC §14)';

    public function handle(TelegramClient $client): int
    {
        if (! $client->enabled()) {
            $this->error('TELEGRAM_BOT_TOKEN yo\'q.');

            return self::FAILURE;
        }

        $url = $this->argument('url')
            ?? rtrim((string) config('telegram.webapp_url'), '/').'/api/telegram/webhook';

        $result = $client->setWebhook($url, (string) config('telegram.webhook_secret'));

        $this->info('Webhook: '.$url);
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
