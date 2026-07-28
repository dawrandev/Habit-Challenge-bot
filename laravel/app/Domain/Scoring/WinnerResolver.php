<?php

declare(strict_types=1);

namespace App\Domain\Scoring;

/**
 * G'olibni aniqlash + tiebreaker — SPEC §4.
 * Ball → ko'proq bajargan → durang.
 */
final class WinnerResolver
{
    /**
     * @return int  1 = A yutdi, -1 = B yutdi, 0 = durang
     */
    public function decide(float $aScore, int $aCompletions, float $bScore, int $bCompletions): int
    {
        if ($aScore !== $bScore) {
            return $aScore > $bScore ? 1 : -1;
        }

        if ($aCompletions !== $bCompletions) {
            return $aCompletions > $bCompletions ? 1 : -1;
        }

        return 0;
    }
}
