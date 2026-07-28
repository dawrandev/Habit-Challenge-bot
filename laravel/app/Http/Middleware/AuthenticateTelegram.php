<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Telegram\InitDataValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Telegram Mini App initData auth — SPEC §2.
 * Lokal test uchun X-Dev-Telegram-Id (allow_dev_auth yoki token yo'q bo'lganda).
 */
class AuthenticateTelegram
{
    private const SUPPORTED_LANGS = ['uz', 'en', 'ru', 'tr'];

    public function __construct(private readonly InitDataValidator $validator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tgUser = $this->resolveTelegramUser($request);

        if ($tgUser === null || ! isset($tgUser['id'])) {
            abort(401, 'Invalid initData');
        }

        $user = $this->upsertUser($tgUser);
        $request->setUserResolver(fn () => $user);

        return $next($request);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveTelegramUser(Request $request): ?array
    {
        $data = $this->validator->validate((string) $request->header('X-Telegram-Init-Data', ''));
        if ($data !== null) {
            return $data['user'];
        }

        $devAllowed = config('telegram.allow_dev_auth') || config('telegram.bot_token') === '';
        $devId = $request->header('X-Dev-Telegram-Id');
        if ($devAllowed && $devId !== null) {
            return ['id' => (int) $devId, 'first_name' => 'Dev'];
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $tg
     */
    private function upsertUser(array $tg): User
    {
        $user = User::firstOrNew(['telegram_id' => (int) $tg['id']]);
        $user->username = $tg['username'] ?? $user->username;
        $user->first_name = $tg['first_name'] ?? ($user->first_name ?: '?');
        $user->photo_url = $tg['photo_url'] ?? $user->photo_url;

        $lang = $tg['language_code'] ?? null;
        if (is_string($lang) && in_array($lang, self::SUPPORTED_LANGS, true)) {
            $user->language = $lang;
        }

        $user->save();

        return $user;
    }
}
