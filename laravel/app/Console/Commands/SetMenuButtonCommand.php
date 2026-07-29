<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Telegram\TelegramClient;
use Illuminate\Console\Command;

class SetMenuButtonCommand extends Command
{
    protected $signature = 'battle:set-menu {text=⚔️ Ochish : Tugma matni}';

    protected $description = 'Bot input yonidagi doimiy menyu tugmasini Mini App ochishga sozlaydi';

    public function handle(TelegramClient $client): int
    {
        if (! $client->enabled()) {
            $this->error('TELEGRAM_BOT_TOKEN yo\'q.');

            return self::FAILURE;
        }

        $url = rtrim((string) config('telegram.webapp_url'), '/').'/';
        $result = $client->setMenuButton((string) $this->argument('text'), $url);

        $this->info('Menyu tugmasi: '.$url);
        $this->line(json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return ($result['ok'] ?? false) ? self::SUCCESS : self::FAILURE;
    }
}
