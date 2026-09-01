<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Duel/missiya davri — foydalanuvchi tanlagan boshlanish va tugash sanasi.
 *
 * Ilgari davr tayyor variantlardan tanlanardi (1 hafta / 2 hafta / 1 oy).
 * SPEC §3 da "erkin sana" ham bor edi, lekin qurilmagan. Endi asosiy —
 * aynan sanalar; tayyor variantlar UI'da faqat tezkor yorliq.
 *
 * Chegaralar SHU YERDA, bitta joyda: FormRequest ham, servis ham shundan
 * oziqlanadi, ya'ni ikkalasi bir-biridan ajralib qolmaydi.
 */
final readonly class DateRange
{
    public const MAX_DAYS = 365;

    private function __construct(
        public CarbonImmutable $start,
        public CarbonImmutable $end,
    ) {}

    public static function fromStrings(string $start, string $end): self
    {
        $tz = (string) config('telegram.timezone');

        $from = CarbonImmutable::parse($start, $tz)->startOfDay();
        $to = CarbonImmutable::parse($end, $tz)->startOfDay();

        if ($to->lessThan($from)) {
            throw new BadRequestHttpException('Tugash sanasi boshlanishdan oldin bo\'la olmaydi');
        }

        $range = new self($from, $to);

        if ($range->days() > self::MAX_DAYS) {
            throw new BadRequestHttpException('Davr '.self::MAX_DAYS.' kundan uzun bo\'la olmaydi');
        }

        return $range;
    }

    /** Inklyuziv kunlar soni: 1-avg..7-avg = 7 kun. */
    public function days(): int
    {
        return (int) $this->start->diffInDays($this->end) + 1;
    }

    public function startDate(): string
    {
        return $this->start->toDateString();
    }

    public function endDate(): string
    {
        return $this->end->toDateString();
    }

    /**
     * FormRequest qoidalari — bugundan oldin boshlanmaydi, teskari bo'lmaydi.
     *
     * "Bugun" SERVER kunidan emas, Toshkent kunidan olinadi: kun oxiri
     * butun ilovada shu mintaqa bo'yicha hisoblanadi (SPEC §3).
     *
     * @return array<string, array<int, string>>
     */
    public static function rules(): array
    {
        $today = Clock::todayLocal()->toDateString();

        return [
            'start_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }
}
