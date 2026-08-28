<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\Quest\CellState;
use App\Domain\Quest\QuestStatsEngine;
use App\Domain\Quest\TrackedChallenge;
use App\Enums\Cadence;
use App\Enums\QuestOutcome;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

/**
 * Missiya statistikasi — sof domen testlari (DB'siz).
 *
 * Kalit invariant: BUGUNGI tugallanmagan ish foizni pasaytirmaydi va
 * seriyani uzmaydi. Kun tugagandan keyingina "missed" bo'ladi.
 */
class QuestStatsEngineTest extends TestCase
{
    private QuestStatsEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new QuestStatsEngine;
    }

    private function day(string $date): CarbonImmutable
    {
        return CarbonImmutable::parse($date, 'Asia/Tashkent')->startOfDay();
    }

    /**
     * @param  array<string>  $approved
     * @param  array<int>  $weekdays
     */
    private function challenge(
        int $id,
        string $start,
        array $approved,
        Cadence $cadence = Cadence::Daily,
        array $weekdays = [],
    ): TrackedChallenge {
        return new TrackedChallenge($id, $cadence, $weekdays, $this->day($start), $approved);
    }

    public function test_all_days_done_gives_full_rate_and_streak(): void
    {
        // 2026-08-01 .. 08-05, bugun 08-05, hammasi bajarilgan
        $ch = $this->challenge(1, '2026-08-01', [
            '2026-08-01', '2026-08-02', '2026-08-03', '2026-08-04', '2026-08-05',
        ]);

        $r = $this->engine->build(
            [$ch],
            $this->day('2026-08-01'),
            $this->day('2026-08-05'),
            $this->day('2026-08-05'),
            80,
        );

        $this->assertSame(5, $r->done);
        $this->assertSame(0, $r->missed);
        $this->assertSame(0, $r->pending);
        $this->assertSame(100.0, $r->rate);
        $this->assertSame(5, $r->currentStreak);
        $this->assertSame(5, $r->longestStreak);
        $this->assertSame(100.0, $r->ceilingRate);
        $this->assertTrue($r->goalReachable);
    }

    public function test_todays_unfinished_work_does_not_lower_the_rate(): void
    {
        // Kecha bajarilgan, bugun hali yo'q — bugun jarima emas.
        $ch = $this->challenge(1, '2026-08-01', ['2026-08-01', '2026-08-02']);

        $r = $this->engine->build(
            [$ch],
            $this->day('2026-08-01'),
            $this->day('2026-08-10'),
            $this->day('2026-08-03'),
            80,
        );

        $this->assertSame(2, $r->done);
        $this->assertSame(0, $r->missed, 'bugungi bajarilmagan ish missed bo`lmasligi kerak');
        $this->assertSame(1, $r->pending);
        $this->assertSame(100.0, $r->rate);
        $this->assertSame(2, $r->currentStreak, 'ochiq bugun seriyani uzmaydi');
    }

    public function test_missed_day_lowers_rate_and_breaks_streak(): void
    {
        // 08-03 o'tkazib yuborilgan (kun tugagan), 08-04/05 bajarilgan
        $ch = $this->challenge(1, '2026-08-01', [
            '2026-08-01', '2026-08-02', '2026-08-04', '2026-08-05',
        ]);

        $r = $this->engine->build(
            [$ch],
            $this->day('2026-08-01'),
            $this->day('2026-08-05'),
            $this->day('2026-08-05'),
            80,
        );

        $this->assertSame(4, $r->done);
        $this->assertSame(1, $r->missed);
        $this->assertSame(80.0, $r->rate);
        $this->assertSame(2, $r->currentStreak, '08-04 va 08-05');
        $this->assertSame(2, $r->longestStreak, '08-01..02 va 08-04..05 — ikkalasi ham 2');
    }

    public function test_rest_days_neither_break_nor_extend_the_streak(): void
    {
        // Faqat Du(0)/Cho(2)/Ju(4). 2026-08-03 = dushanba.
        $ch = $this->challenge(
            1,
            '2026-08-03',
            ['2026-08-03', '2026-08-05', '2026-08-07'],
            Cadence::WeeklyDays,
            [0, 2, 4],
        );

        $r = $this->engine->build(
            [$ch],
            $this->day('2026-08-03'),
            $this->day('2026-08-09'),
            $this->day('2026-08-09'),   // yakshanba — navbat yo'q
            80,
        );

        $this->assertSame(3, $r->done);
        $this->assertSame(0, $r->missed);
        $this->assertSame(3, $r->currentStreak, 'oradagi dam kunlari seriyani uzmaydi');
        $this->assertSame(100.0, $r->rate);

        // 7 kundan faqat 3 tasi navbatda
        $this->assertSame(3, $r->planned);
    }

    public function test_ceiling_rate_falls_with_misses_and_kills_an_unreachable_goal(): void
    {
        // 10 kunlik davr, dastlabki 3 kun o'tkazib yuborilgan → shift 70%
        $ch = $this->challenge(1, '2026-08-01', []);

        $r = $this->engine->build(
            [$ch],
            $this->day('2026-08-01'),
            $this->day('2026-08-10'),
            $this->day('2026-08-04'),
            80,
        );

        $this->assertSame(3, $r->missed);
        $this->assertSame(10, $r->planned);
        $this->assertSame(70.0, $r->ceilingRate);
        $this->assertFalse($r->goalReachable, '80% maqsadga endi yetib bo`lmaydi');
    }

    public function test_partial_day_is_not_perfect_and_breaks_the_streak(): void
    {
        // Ikki challenge; 08-02 da faqat bittasi bajarilgan → mukammal kun emas
        $a = $this->challenge(1, '2026-08-01', ['2026-08-01', '2026-08-02', '2026-08-03']);
        $b = $this->challenge(2, '2026-08-01', ['2026-08-01', '2026-08-03']);

        $r = $this->engine->build(
            [$a, $b],
            $this->day('2026-08-01'),
            $this->day('2026-08-03'),
            $this->day('2026-08-03'),
            80,
        );

        $this->assertSame(5, $r->done);
        $this->assertSame(1, $r->missed);
        $this->assertSame(1, $r->currentStreak, 'faqat 08-03');

        $states = array_map(fn ($d) => $d->state, $r->days);
        $this->assertSame(
            [CellState::Done, CellState::Partial, CellState::Done],
            $states,
        );
    }

    public function test_challenge_added_mid_quest_is_not_penalized_retroactively(): void
    {
        // Missiya 08-01 da boshlangan, ikkinchi challenge 08-04 da qo'shilgan
        $a = $this->challenge(1, '2026-08-01', [
            '2026-08-01', '2026-08-02', '2026-08-03', '2026-08-04', '2026-08-05',
        ]);
        $b = $this->challenge(2, '2026-08-04', ['2026-08-04', '2026-08-05']);

        $r = $this->engine->build(
            [$a, $b],
            $this->day('2026-08-01'),
            $this->day('2026-08-05'),
            $this->day('2026-08-05'),
            80,
        );

        $this->assertSame(7, $r->done, '5 + 2');
        $this->assertSame(0, $r->missed, 'qo`shilishdan oldingi kunlar jarima emas');
        $this->assertSame(100.0, $r->rate);

        // b challenge'ning 08-01..03 kataklari "dam" (navbat emas)
        $bStat = collect($r->challenges)->firstWhere('challengeId', 2);
        $this->assertSame(
            [CellState::Rest->value, CellState::Rest->value, CellState::Rest->value],
            array_slice($bStat->cells, 0, 3),
        );
    }

    public function test_finished_quest_resolves_the_last_day_and_sets_outcome(): void
    {
        // 4/5 bajarilgan = 80% — maqsad ayni 80% → erishildi
        $ch = $this->challenge(1, '2026-08-01', [
            '2026-08-01', '2026-08-02', '2026-08-03', '2026-08-04',
        ]);

        $r = $this->engine->build(
            [$ch],
            $this->day('2026-08-01'),
            $this->day('2026-08-05'),
            $this->day('2026-08-05'),
            80,
            finished: true,
        );

        $this->assertSame(0, $r->pending, 'yakunlanganda ochiq kun qolmaydi');
        $this->assertSame(1, $r->missed);
        $this->assertSame(80.0, $r->rate);
        $this->assertSame(QuestOutcome::Achieved, $r->outcome);
    }

    public function test_finished_quest_below_goal_is_missed(): void
    {
        $ch = $this->challenge(1, '2026-08-01', ['2026-08-01', '2026-08-02', '2026-08-03']);

        $r = $this->engine->build(
            [$ch],
            $this->day('2026-08-01'),
            $this->day('2026-08-05'),
            $this->day('2026-08-05'),
            80,
            finished: true,
        );

        $this->assertSame(60.0, $r->rate);
        $this->assertSame(QuestOutcome::Missed, $r->outcome);
    }

    public function test_per_challenge_breakdown_tracks_its_own_streak(): void
    {
        $a = $this->challenge(1, '2026-08-01', ['2026-08-01', '2026-08-02', '2026-08-03']);
        $b = $this->challenge(2, '2026-08-01', ['2026-08-01']);

        $r = $this->engine->build(
            [$a, $b],
            $this->day('2026-08-01'),
            $this->day('2026-08-03'),
            $this->day('2026-08-03'),
            80,
        );

        $stats = collect($r->challenges)->keyBy('challengeId');

        $this->assertSame(3, $stats[1]->done);
        $this->assertSame(3, $stats[1]->currentStreak);
        $this->assertSame(100.0, $stats[1]->rate);

        // 08-02 o'tkazib yuborilgan; 08-03 esa BUGUN va hali ochiq → pending, missed emas
        $this->assertSame(1, $stats[2]->done);
        $this->assertSame(1, $stats[2]->missed);
        $this->assertSame(1, $stats[2]->pending);
        $this->assertSame(0, $stats[2]->currentStreak, '08-02 seriyani uzdi');
        $this->assertSame(1, $stats[2]->longestStreak);
        $this->assertSame(50.0, $stats[2]->rate, 'hal bo`lgan 2 slotdan 1 tasi');
    }

    public function test_day_cells_align_one_to_one_with_challenge_cells(): void
    {
        // Chart uchun hayotiy muhim: heatmap qatorlari sana o'qi bilan mos kelishi shart
        $a = $this->challenge(1, '2026-08-01', ['2026-08-02']);
        $b = $this->challenge(2, '2026-08-01', [], Cadence::WeeklyDays, [5, 6]);

        $r = $this->engine->build(
            [$a, $b],
            $this->day('2026-08-01'),
            $this->day('2026-08-07'),
            $this->day('2026-08-04'),
            80,
        );

        $this->assertCount(7, $r->days);
        foreach ($r->challenges as $stat) {
            $this->assertCount(7, $stat->cells, 'har challenge qatori kunlar soniga teng');
        }
    }

    public function test_empty_quest_does_not_divide_by_zero(): void
    {
        $r = $this->engine->build(
            [],
            $this->day('2026-08-01'),
            $this->day('2026-08-07'),
            $this->day('2026-08-04'),
            80,
        );

        $this->assertSame(0, $r->planned);
        $this->assertSame(0.0, $r->rate);
        $this->assertSame(0, $r->currentStreak);
        $this->assertSame(100.0, $r->ceilingRate);
        $this->assertCount(7, $r->days);
    }
}
