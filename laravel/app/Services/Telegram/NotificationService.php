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
}
