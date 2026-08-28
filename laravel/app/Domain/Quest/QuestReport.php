<?php

declare(strict_types=1);

namespace App\Domain\Quest;

use App\Enums\QuestOutcome;

/**
 * Missiyaning to'liq kesimi — barcha chartlarning yagona manbasi.
 *
 * Atamalar:
 *   slot          = (challenge × navbatdagi kun) juftligi
 *   done          = tasdiqlangan slot
 *   missed        = kun tugadi, tasdiq yo'q
 *   pending       = bugungi, hali hal bo'lmagan
 *   resolved      = done + missed (hal bo'lgan slotlar) — FOIZ MAXRAJI
 *   planned       = butun davr bo'yicha barcha slotlar
 *
 * Bugungi bajarilmagan slot jarima EMAS (kun tugamagan) — duel scoring'i bilan
 * bir xil falsafa (SPEC §4).
 */
final readonly class QuestReport implements \JsonSerializable
{
    /**
     * @param  array<DayCell>  $days
     * @param  array<ChallengeStat>  $challenges
     */
    public function __construct(
        public int $done,
        public int $missed,
        public int $pending,
        public int $planned,
        public float $rate,          // 0..100 — hal bo'lgan slotlardan
        public float $ceilingRate,   // 0..100 — bundan buyon hammasi bajarilsa, maksimum
        public int $currentStreak,
        public int $longestStreak,
        public int $daysElapsed,
        public int $daysTotal,
        public int $goalPercent,
        public bool $goalReachable,
        public ?QuestOutcome $outcome,
        public array $days,
        public array $challenges,
    ) {}

    /** Hal bo'lgan slotlar — foiz maxraji. */
    public function resolved(): int
    {
        return $this->done + $this->missed;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'done' => $this->done,
            'missed' => $this->missed,
            'pending' => $this->pending,
            'resolved' => $this->resolved(),
            'planned' => $this->planned,
            'rate' => $this->rate,
            'ceiling_rate' => $this->ceilingRate,
            'current_streak' => $this->currentStreak,
            'longest_streak' => $this->longestStreak,
            'days_elapsed' => $this->daysElapsed,
            'days_total' => $this->daysTotal,
            'goal_percent' => $this->goalPercent,
            'goal_reachable' => $this->goalReachable,
            'outcome' => $this->outcome?->value,
            'days' => $this->days,
            'challenges' => $this->challenges,
        ];
    }
}
