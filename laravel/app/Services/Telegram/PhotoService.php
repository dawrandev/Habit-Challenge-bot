<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Rasm Telegram kanalida saqlanadi (file_id) + proxy — SPEC §10.
 */
class PhotoService
{
    public function __construct(private readonly TelegramClient $client) {}

    /**
     * Rasm baytlarini maxsus kanalga yuboradi, file_id qaytaradi.
     */
    public function store(string $contents, string $filename = 'proof.jpg'): string
    {
        $chatId = config('telegram.storage_chat_id');

        if (! $this->client->enabled() || ! $chatId) {
            // DEV: bot token yo'q — placeholder
            return 'dev-'.substr(hash('sha256', $contents.microtime()), 0, 16);
        }

        $result = $this->client->sendPhoto($chatId, $contents, $filename);
        /** @var array<int, array{file_id: string}> $photos */
        $photos = $result['photo'] ?? [];
        $last = end($photos);

        return $last['file_id'] ?? '';
    }

    /**
     * file_id bo'yicha rasm baytlari (proxy — token oshkor bo'lmaydi).
     */
    public function fetch(string $fileId): ?string
    {
        $path = $this->client->getFilePath($fileId);
        if ($path === null) {
            return null;
        }

        return $this->client->downloadFile($path);
    }
}
