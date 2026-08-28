<?php

declare(strict_types=1);

namespace App\Domain\Quest;

/**
 * Tracker to'ridagi bitta kun (barcha challenge'lar bo'yicha yig'ma).
 */
final readonly class DayCell implements \JsonSerializable
{
    public function __construct(
        public string $date,      // Y-m-d
        public int $due,          // shu kuni navbatdagi challenge soni
        public int $done,         // tasdiqlangani
        public CellState $state,
    ) {}

    /** Barcha navbatdagilar bajarilgan "mukammal kun" — streak shunga qaraydi. */
    public function isPerfect(): bool
    {
        return $this->due > 0 && $this->done === $this->due;
    }

    public function isRest(): bool
    {
        return $this->due === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'date' => $this->date,
            'due' => $this->due,
            'done' => $this->done,
            'state' => $this->state->value,
        ];
    }
}
