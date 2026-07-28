<?php

declare(strict_types=1);

return [
    // Telegram bot
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'bot_username' => env('TELEGRAM_BOT_USERNAME', ''),
    'storage_chat_id' => env('TELEGRAM_STORAGE_CHAT_ID', ''),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
    'api_base' => env('TELEGRAM_API_BASE', 'https://api.telegram.org'),

    // Mini App
    'webapp_url' => env('TELEGRAM_WEBAPP_URL', 'http://localhost:5173'),

    // Auth: lokal brauzer testi uchun (PROD'DA false)
    'allow_dev_auth' => (bool) env('TELEGRAM_ALLOW_DEV_AUTH', false),
    'init_data_ttl' => (int) env('TELEGRAM_INIT_DATA_TTL', 86400),

    // Vaqt mintaqasi (kun oxiri shu bo'yicha) — SPEC §10
    'timezone' => env('BATTLE_TIMEZONE', 'Asia/Tashkent'),

    // Scoring — SPEC §4
    'scoring' => [
        'points_per_completion' => (float) env('SCORE_POINTS', 1.0),
        'penalty_per_miss' => (float) env('SCORE_PENALTY', 0.5),
        'floor' => (float) env('SCORE_FLOOR', 0.0),
    ],

    // Tekshiruv deadline (soat) — SPEC §5, §11
    'verify_deadline_hours' => (int) env('VERIFY_DEADLINE_HOURS', 24),
];
