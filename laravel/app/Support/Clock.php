<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Battle vaqt mintaqasi bo'yicha vaqt — SPEC §10 (default Asia/Tashkent).
 */
final class Clock
{
    public static function nowLocal(): CarbonImmutable
    {
        return CarbonImmutable::now((string) config('telegram.timezone'));
    }

    public static function todayLocal(): CarbonImmutable
    {
        return self::nowLocal()->startOfDay();
    }
}
