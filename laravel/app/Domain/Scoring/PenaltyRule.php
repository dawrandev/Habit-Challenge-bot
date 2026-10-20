<?php

declare(strict_types=1);

namespace App\Domain\Scoring;

/**
 * Jazo chegarasi — SPEC §17. Framework'dan mustaqil.
 *
 * Rejadagi odatlarning `thresholdPercent` foizi YOKI undan kamini bajargan
 * o'yinchi jazoga tortiladi. Misol (50%): 60 tadan 30 tasi — jazo, 31 tasi — xavfsiz.
 *
 * Butun sonlarda hisoblanadi — foizni yaxlitlash chegarada xato qilmasin.
 */
final readonly class PenaltyRule
{
    public function __construct(public int $thresholdPercent = 50) {}

    public function isPenalized(int $done, int $expected): bool
    {
        // Rejada hech narsa bo'lmasa — jazolaydigan narsa ham yo'q
        if ($expected <= 0) {
            return false;
        }

        return $done * 100 <= $expected * $this->thresholdPercent;
    }

    /** Jazodan qutulish uchun eng kamida nechta bajarish kerak. */
    public function safeAt(int $expected): int
    {
        if ($expected <= 0) {
            return 0;
        }

        return intdiv($expected * $this->thresholdPercent, 100) + 1;
    }
}
