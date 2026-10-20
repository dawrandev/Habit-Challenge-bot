<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;

/**
 * Kechagi hisobot oynasi — SPEC §18.
 *
 * Odam odatni bajarib, isbot yuborishni unutishi mumkin. Shuning uchun
 * o'tgan `late_proof_days` kun (standart: faqat kecha) uchun ham isbot
 * qabul qilinadi. Undan eskisi — yo'q: jonli kamera isbotining ma'nosi
 * "yaqinda bajarildi" bo'lishida.
 *
 * Duel/missiya ham shu oyna yopilgandan KEYIN yakunlanadi — aks holda
 * oxirgi kun uchun kechagi hisobot imkoni bo'lmasdi.
 */
final class LateWindow
{
    public static function days(): int
    {
        return max(0, (int) config('telegram.late_proof_days', 1));
    }

    /** Hali isbot qabul qiladigan eng eski kun. */
    public static function earliestOpenDay(): CarbonImmutable
    {
        return Clock::todayLocal()->subDays(self::days());
    }

    /** Shu kun ('Y-m-d') uchun isbot yuborsa bo'ladimi (bugun ham kiradi). */
    public static function accepts(string $day): bool
    {
        return $day >= self::earliestOpenDay()->toDateString()
            && $day <= Clock::todayLocal()->toDateString();
    }

    /**
     * Shu sanada tugagan duel/missiyani yopsa bo'ladimi — ya'ni uning
     * oxirgi kuni uchun ham kechagi hisobot oynasi yopilganmi.
     */
    public static function isPast(string $endDate): bool
    {
        return $endDate < self::earliestOpenDay()->toDateString();
    }
}
