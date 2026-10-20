<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Notifikatsiyalar — SPEC §7.
 */
class NotificationService
{
    public function __construct(private readonly TelegramClient $client) {}

    /**
     * @param  iterable<int>  $telegramIds
     */
    public function notify(iterable $telegramIds, string $text): void
    {
        foreach ($telegramIds as $id) {
            $this->client->sendMessage($id, $text);
        }
    }

    /**
     * Mini App'ni kerakli sahifada ochadigan tugma bilan xabar (eslatmalar — SPEC §18).
     *
     * @param  string  $path  ilova ichidagi yo'l, masalan '/battle/5'
     */
    public function notifyWithApp(int $telegramId, string $text, string $buttonText, string $path = '/'): void
    {
        $base = rtrim((string) config('telegram.webapp_url'), '/');

        $this->client->sendWebApp($telegramId, $text, $buttonText, $base.$path);
    }
}
