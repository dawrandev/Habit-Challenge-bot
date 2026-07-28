<?php

declare(strict_types=1);

namespace App\Domain\Scoring;

use App\Enums\Cadence;
use Carbon\CarbonImmutable;

/**
 * Scoring engine — SPEC §4. Framework'dan mustaqil, sof biznes-logika.
 *
 * Qoidalar:
 *   - Tasdiqlangan bajarish: +points_per_completion (1.0)
 *   - O'tkazib yuborilgan navbatdagi kun (kun tugagan): -penalty_per_miss (0.5)
 *   - Bugungi hali hal bo'lmagan kun: 0
 *   - Umumiy hisob floor (0) dan past bo'lmaydi
 */
final class ScoringEngine
{
    public function __construct(
        private readonly float $pointsPerCompletion = 1.0,
        private readonly float $penaltyPerMiss = 0.5,
        private readonly float $floor = 0.0,
    ) {}

    public static function fromConfig(): self
    {
        /** @var array{points_per_completion: float, penalty_per_miss: float, floor: float} $c */
        $c = config('telegram.scoring');

        return new self($c['points_per_completion'], $c['penalty_per_miss'], $c['floor']);
    }

    /**
     * Navbatdagi kunlar [start..until].
     *
     * @param  array<int>  $weekdays
     * @return array<CarbonImmutable>
     */
    public function dueDays(Cadence $cadence, array $weekdays, CarbonImmutable $start, CarbonImmutable $until): array
    {
        $days = [];
        for ($day = $start; $day->lessThanOrEqualTo($until); $day = $day->addDay()) {
            if ($cadence->isDue($day, $weekdays)) {
                $days[] = $day;
            }
        }

        return $days;
    }

    /**
     * Bitta ishtirokchining UMUMIY balli.
     *
     * @param  array<ChallengeScore>  $challenges
     */
    public function participantScore(array $challenges, CarbonImmutable $today, CarbonImmutable $endDate): float
    {
        $lastDay = $today->lessThanOrEqualTo($endDate) ? $today : $endDate;
        $total = 0.0;

        foreach ($challenges as $challenge) {
            foreach ($this->dueDays($challenge->cadence, $challenge->weekdays, $challenge->startDate, $lastDay) as $day) {
                if ($challenge->isApproved($day)) {
                    $total += $this->pointsPerCompletion;
                } elseif ($day->lessThan($today)) {
                    // kun tugagan, tasdiqlangan bajarish yo'q → jarima
                    $total -= $this->penaltyPerMiss;
                }
                // bugun va tasdiqlanmagan → 0 (kun tugamagan)
            }
        }

        return max($this->floor, $total);
    }

    /**
     * UI uchun: shu challenge bo'yicha tasdiqlangan kunlar soni.
     */
    public function challengeCompletions(ChallengeScore $challenge, CarbonImmutable $today, CarbonImmutable $endDate): int
    {
        $lastDay = $today->lessThanOrEqualTo($endDate) ? $today : $endDate;
        $count = 0;

        foreach ($this->dueDays($challenge->cadence, $challenge->weekdays, $challenge->startDate, $lastDay) as $day) {
            if ($challenge->isApproved($day)) {
                $count++;
            }
        }

        return $count;
    }
}
