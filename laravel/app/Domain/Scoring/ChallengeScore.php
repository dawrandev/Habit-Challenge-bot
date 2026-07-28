<?php

declare(strict_types=1);

namespace App\Domain\Scoring;

use App\Enums\Cadence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Scoring uchun challenge ma'lumoti (value object) — framework'dan mustaqil.
 */
final readonly class ChallengeScore
{
    /**
     * @param  array<int>  $weekdays  0=Dushanba .. 6=Yakshanba
     * @param  array<string>  $approvedDays  tasdiqlangan kunlar ('Y-m-d')
     */
    public function __construct(
        public Cadence $cadence,
        public array $weekdays,
        public CarbonImmutable $startDate,
        public array $approvedDays,
    ) {}

    public function isApproved(CarbonInterface $day): bool
    {
        return in_array($day->format('Y-m-d'), $this->approvedDays, true);
    }
}
