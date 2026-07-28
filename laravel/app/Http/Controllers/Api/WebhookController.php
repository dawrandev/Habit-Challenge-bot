<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\TelegramClient;
use Illuminate\Http\Request;

/**
 * Telegram bot webhook — /start ga WebApp tugmasi bilan javob (SPEC §14).
 * (Laravel/PHP'da bot polling emas, webhook — domen+SSL bo'lgach ishlaydi.)
 */
class WebhookController extends Controller
{
    public function __construct(private readonly TelegramClient $client) {}

    public function handle(Request $request)
    {
        // Secret token tekshiruvi (BotFather setWebhook secret_token)
        $secret = (string) config('telegram.webhook_secret');
        if ($secret !== '' && $request->header('X-Telegram-Bot-Api-Secret-Token') !== $secret) {
            abort(403);
        }

        $message = $request->input('message');
        $text = $message['text'] ?? '';
        $chatId = $message['chat']['id'] ?? null;

        if ($chatId !== null && str_starts_with((string) $text, '/start')) {
            $param = trim(str_replace('/start', '', (string) $text));
            $url = (string) config('telegram.webapp_url');
            if ($param !== '') {
                $url .= '?tgWebAppStartParam='.urlencode($param);
            }

            $this->client->sendWebApp(
                $chatId,
                "<b>Battle</b> — odat dueli.\nDo'stingni chaqir, har kuni isbot yubor, "
                    ."bir-biringni tekshir, g'olib bo'l! 🔥",
                '⚔️ Ochish',
                $url,
            );
        }

        return response()->json(['ok' => true]);
    }
}
