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
        try {
            return Http::get($this->method('getFile'), ['file_id' => $fileId])
                ->json('result.file_path');
        } catch (\Throwable $e) {
            $this->logFailure('getFile', $e);

            return null;
        }
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

        return $this->call(fn () => Http::asJson()->post($this->method('setWebhook'), $payload)->json(), 'setWebhook');
    }

    public function getMe(): array
    {
        return $this->call(fn () => Http::get($this->method('getMe'))->json(), 'getMe');
    }

    /**
     * Webhook holati — diagnostika uchun (battle:doctor).
     *
     * @return array<string, mixed>
     */
    public function getWebhookInfo(): array
    {
        $result = $this->call(
            fn () => ['ok' => true, 'result' => Http::get($this->method('getWebhookInfo'))->json('result', [])],
            'getWebhookInfo',
        );

        return (array) ($result['result'] ?? []);
    }

    /**
     * Doimiy menyu tugmasi (input yonida) — Mini App'ni ochadi.
     */
    public function setMenuButton(string $text, string $url): array
    {
        return $this->call(fn () => Http::asJson()->post($this->method('setChatMenuButton'), [
            'menu_button' => [
                'type' => 'web_app',
                'text' => $text,
                'web_app' => ['url' => $url],
            ],
        ])->json(), 'setChatMenuButton');
    }

    /**
     * Telegram chaqiruvini xavfsiz bajaradi.
     *
     * Tarmoq uzilishi ISTISNO EMAS, kutilgan holat: server DNS'siz qolishi
     * mumkin. Diagnostika buyrug'i shunday paytda muammoni XABAR QILISHI
     * kerak, qulab tushmasligi.
     *
     * @param  callable(): mixed  $request
     * @return array<string, mixed>
     */
    private function call(callable $request, string $method): array
    {
        try {
            return (array) $request();
        } catch (\Throwable $e) {
            $this->logFailure($method, $e);

            return ['ok' => false, 'error' => $this->safeMessage($e)];
        }
    }

    private function logFailure(string $method, \Throwable $e): void
    {
        Log::warning("telegram.{$method} failed", ['error' => $this->safeMessage($e)]);
    }

    /**
     * Xato matnidan bot TOKENINI olib tashlaydi.
     *
     * Guzzle xatosi to'liq URL'ni qaytaradi, unda esa token bor — u log'ga
     * yoki ekranga tushsa oshkor bo'ladi.
     */
    private function safeMessage(\Throwable $e): string
    {
        $message = $e->getMessage();

        if ($this->token !== '') {
            $message = str_replace($this->token, '***', $message);
        }

        return preg_replace('/bot\d+:[A-Za-z0-9_-]+/', 'bot***', $message) ?? 'xato';
    }
}
