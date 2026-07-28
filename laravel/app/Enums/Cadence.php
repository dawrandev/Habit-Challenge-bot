<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;

/**
 * Challenge bajarilish chastotasi — SPEC §3, §7.
 */
enum Cadence: string
{
    case Daily = 'daily';               // har kuni
    case WeeklyDays = 'weekly_days';    // muayyan hafta kunlari

    /**
     * Shu kun challenge navbatdami?
     *
     * @param  array<int>  $weekdays  0=Dushanba .. 6=Yakshanba
     */
    public function isDue(CarbonInterface $day, array $weekdays): bool
    {
        return match ($this) {
            self::Daily => true,
            // Carbon: Monday=1..Sunday=7 → 0..6 ga o'tkazamiz
            self::WeeklyDays => in_array($day->dayOfWeekIso - 1, $weekdays, true),
        };
    }
}
