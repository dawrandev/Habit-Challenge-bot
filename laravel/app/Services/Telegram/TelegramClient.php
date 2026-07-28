<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Bot API bilan past darajali ishlash (HTTP).
 */
class TelegramClient
{
    private string $token;

    private string $base;

    public function __construct()
    {
        $this->token = (string) config('telegram.bot_token');
        $this->base = rtrim((string) config('telegram.api_base'), '/');
    }

    public function enabled(): bool
    {
        return $this->token !== '';
    }

    private function method(string $name): string
    {
        return "{$this->base}/bot{$this->token}/{$name}";
    }

    public function sendMessage(int|string $chatId, string $text): void
    {
        if (! $this->enabled()) {
            return;
        }

        try {
            Http::asJson()->post($this->method('sendMessage'), [
                'chat_id' => $chatId,
                'text' => $text,
                'parse_mode' => 'HTML',
            ]);
        } catch (\Throwable $e) {
            Log::warning('telegram.sendMessage failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Rasmni yuboradi, natijani (photo massivini) qaytaradi — SPEC §10.
     *
     * @return array<string, mixed>
     */
    public function sendPhoto(int|string $chatId, string $contents, string $filename = 'proof.jpg'): array
    {
        $response = Http::attach('photo', $contents, $filename)
            ->post($this->method('sendPhoto'), ['chat_id' => $chatId]);

        return (array) $response->json('result', []);
    }

    /**
     * WebApp tugmasi bilan xabar (bot /start) — SPEC §2, §14.
     */
    public function sendWebApp(int|string $chatId, string $text, string $buttonText, string $url): void
    {
        if (! $this->enabled()) {
            return;
        }

        Http::asJson()->post($this->method('sendMessage'), [
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => [
                'inline_keyboard' => [[
                    ['text' => $buttonText, 'web_app' => ['url' => $url]],
                ]],
            ],
        ]);
    }

    public function getFilePath(string $fileId): ?string
    {
        $response = Http::get($this->method('getFile'), ['file_id' => $fileId]);

        return $response->json('result.file_path');
    }

    public function downloadFile(string $filePath): string
    {
        return Http::get("{$this->base}/file/bot{$this->token}/{$filePath}")->body();
    }

    public function setWebhook(string $url, string $secret = ''): array
    {
        $payload = ['url' => $url, 'drop_pending_updates' => true];
        if ($secret !== '') {
            $payload['secret_token'] = $secret;
        }

        return (array) Http::asJson()->post($this->method('setWebhook'), $payload)->json();
    }

    public function getMe(): array
    {
        return (array) Http::get($this->method('getMe'))->json();
    }
}
