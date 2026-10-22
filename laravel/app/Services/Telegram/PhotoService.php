<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Support\Facades\File;

/**
 * Rasm (va video) Telegram kanalida saqlanadi (file_id) + proxy — SPEC §10.
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
     * Video baytlarini maxsus kanalga yuboradi, file_id qaytaradi.
     */
    public function storeVideo(string $contents, string $filename = 'proof.mp4'): string
    {
        $chatId = config('telegram.storage_chat_id');

        if (! $this->client->enabled() || ! $chatId) {
            return 'dev-'.substr(hash('sha256', $contents.microtime()), 0, 16);
        }

        $result = $this->client->sendVideo($chatId, $contents, $filename);

        return (string) ($result['video']['file_id'] ?? $result['document']['file_id'] ?? '');
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

    /**
     * Faylni diskka keshlab, yo'lini qaytaradi (video proxy uchun).
     *
     * Video Range so'rovlari bilan o'qiladi (iOS webview usiz o'ynatmaydi) —
     * har bo'lak uchun Telegram'dan qayta yuklamaslik uchun fayl bir marta
     * diskka tushadi. file_id o'zgarmas, shuning uchun kesh eskirmaydi.
     */
    public function cachedPath(string $fileId): ?string
    {
        $dir = storage_path('app/media-cache');
        $path = $dir.'/'.hash('sha256', $fileId);

        if (is_file($path)) {
            return $path;
        }

        $data = $this->fetch($fileId);
        if ($data === null || $data === '') {
            return null;
        }

        File::ensureDirectoryExists($dir);
        // Atomik yozish — parallel so'rov chala faylni o'qib qolmasin
        $tmp = $path.'.'.bin2hex(random_bytes(4)).'.tmp';
        file_put_contents($tmp, $data);
        rename($tmp, $path);

        return $path;
    }
}
