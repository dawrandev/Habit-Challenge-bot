<?php

declare(strict_types=1);

namespace App\Domain\Quest;

use App\Domain\Scoring\ChallengeScore;
use App\Enums\Cadence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Missiya statistikasi uchun bitta challenge — framework'dan mustaqil.
 *
 * @see ChallengeScore  duel uchun analog (ball hisoblaydi;
 *      bu esa bajarish foizi va streak hisoblaydi — maqsadi boshqa)
 */
final readonly class TrackedChallenge
{
    /**
     * @param  array<int>  $weekdays  0=Dushanba .. 6=Yakshanba
     * @param  array<string>  $approvedDays  tasdiqlangan kunlar ('Y-m-d')
     */
    public function __construct(
        public int $id,
        public Cadence $cadence,
        public array $weekdays,
        public CarbonImmutable $startDate,
        public array $approvedDays,
    ) {}

    public function isDue(CarbonInterface $day): bool
    {
        if ($day->lessThan($this->startDate)) {
            return false;
        }

        return $this->cadence->isDue($day, $this->weekdays);
    }

    public function isApproved(CarbonInterface $day): bool
    {
        return in_array($day->format('Y-m-d'), $this->approvedDays, true);
    }
}
