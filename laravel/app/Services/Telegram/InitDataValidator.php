<?php

declare(strict_types=1);

namespace App\Services\Telegram;

/**
 * Telegram Mini App initData imzo tekshiruvi — SPEC §2.
 * https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 */
class InitDataValidator
{
    /**
     * @return array{user: array<string, mixed>, auth_date: int}|null
     */
    public function validate(string $initData): ?array
    {
        $token = (string) config('telegram.bot_token');
        if ($initData === '' || $token === '') {
            return null;
        }

        parse_str($initData, $params);
        $hash = $params['hash'] ?? null;
        unset($params['hash']);

        if (! is_string($hash)) {
            return null;
        }

        ksort($params);
        $dataCheckString = collect($params)
            ->map(fn ($value, $key) => "{$key}={$value}")
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $token, 'WebAppData', true);
        $calculated = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($calculated, $hash)) {
            return null;
        }

        // auth_date TTL tekshiruvi (qayta ishlatishga qarshi)
        $authDate = (int) ($params['auth_date'] ?? 0);
        $ttl = (int) config('telegram.init_data_ttl');
        if ($ttl > 0 && $authDate > 0 && (time() - $authDate) > $ttl) {
            return null;
        }

        $user = [];
        if (isset($params['user']) && is_string($params['user'])) {
            $decoded = json_decode($params['user'], true);
            if (is_array($decoded)) {
                $user = $decoded;
            }
        }

        return ['user' => $user, 'auth_date' => $authDate];
    }
}
