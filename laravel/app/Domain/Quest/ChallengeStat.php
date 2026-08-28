<?php

declare(strict_types=1);

namespace App\Domain\Quest;

/**
 * Bitta challenge bo'yicha kesim (chart: gorizontal barlar + heatmap qatori).
 */
final readonly class ChallengeStat implements \JsonSerializable
{
    /**
     * @param  array<string>  $cells  kunlar ro'yxatiga MOS keladigan holat kodlari
     *                                (QuestReport::$days bilan bir xil uzunlik va tartib)
     */
    public function __construct(
        public int $challengeId,
        public int $done,
        public int $missed,
        public int $pending,
        public float $rate,
        public int $currentStreak,
        public int $longestStreak,
        public array $cells,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'challenge_id' => $this->challengeId,
            'done' => $this->done,
            'missed' => $this->missed,
            'pending' => $this->pending,
            'rate' => $this->rate,
            'current_streak' => $this->currentStreak,
            'longest_streak' => $this->longestStreak,
            'cells' => $this->cells,
        ];
    }
}
