<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\Quest;
use App\Support\Clock;
use App\Support\DateRange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Davr endi aynan sanalar bilan belgilanadi (SPEC §3 "erkin sana").
 *
 * `period_days` — kirish emas, sanalardan HOSILA. Ikki manba bo'lsa ular
 * bir-biridan ajralib ketardi, shuning uchun bu yerda mixlab qo'yilgan.
 */
class PeriodDatesTest extends TestCase
{
    use RefreshDatabase;

    private const TG = 5001;

    private function asTg(int $telegramId = self::TG): self
    {
        return $this->withHeader('X-Dev-Telegram-Id', (string) $telegramId);
    }

    /**
     * @return array<string, mixed>
     */
    private function battlePayload(string $start, string $end): array
    {
        return [
            'title' => 'Sanali duel',
            'start_date' => $start,
            'end_date' => $end,
            'challenges' => [[
                'name' => 'Sport',
                'icon' => '🏃',
                'cadence' => 'daily',
                'weekdays' => [],
            ]],
        ];
    }

    public function test_period_days_is_derived_from_the_chosen_dates(): void
    {
        $start = Clock::todayLocal();
        $end = $start->addDays(9);   // 10 kun (inklyuziv)

        $this->asTg()
            ->postJson('/api/battles', $this->battlePayload($start->toDateString(), $end->toDateString()))
            ->assertStatus(200);

        $battle = Battle::firstOrFail();
        $this->assertSame(10, $battle->period_days);
        $this->assertSame($start->toDateString(), $battle->start_date->toDateString());
        $this->assertSame($end->toDateString(), $battle->end_date->toDateString());
    }

    public function test_single_day_period_is_allowed(): void
    {
        $today = Clock::todayLocal()->toDateString();

        $this->asTg()
            ->postJson('/api/battles', $this->battlePayload($today, $today))
            ->assertStatus(200);

        $this->assertSame(1, Battle::firstOrFail()->period_days);
    }

    public function test_battle_can_start_in_the_future(): void
    {
        $start = Clock::todayLocal()->addDays(5);
        $end = $start->addDays(6);

        $this->asTg()
            ->postJson('/api/battles', $this->battlePayload($start->toDateString(), $end->toDateString()))
            ->assertStatus(200);

        $battle = Battle::firstOrFail();
        $this->assertSame($start->toDateString(), $battle->start_date->toDateString());
        $this->assertSame(7, $battle->period_days);

        // Hali boshlanmagan — bugun hech narsa kutilmaydi
        $this->asTg()
            ->getJson("/api/battles/{$battle->id}/today")
            ->assertStatus(200)
            ->assertJsonCount(0, null);
    }

    public function test_start_in_the_past_is_rejected(): void
    {
        $yesterday = Clock::todayLocal()->subDay()->toDateString();
        $end = Clock::todayLocal()->addDays(6)->toDateString();

        $this->asTg()
            ->postJson('/api/battles', $this->battlePayload($yesterday, $end))
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_date');
    }

    public function test_end_before_start_is_rejected(): void
    {
        $start = Clock::todayLocal()->addDays(5)->toDateString();
        $end = Clock::todayLocal()->addDays(2)->toDateString();

        $this->asTg()
            ->postJson('/api/battles', $this->battlePayload($start, $end))
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    public function test_period_longer_than_the_cap_is_rejected(): void
    {
        $start = Clock::todayLocal();
        $end = $start->addDays(DateRange::MAX_DAYS);   // cap + 1 kun

        $this->asTg()
            ->postJson('/api/battles', $this->battlePayload($start->toDateString(), $end->toDateString()))
            ->assertStatus(400);

        $this->assertSame(0, Battle::count());
    }

    public function test_malformed_date_is_rejected(): void
    {
        $this->asTg()
            ->postJson('/api/battles', $this->battlePayload('28.08.2026', '2026-09-04'))
            ->assertStatus(422)
            ->assertJsonValidationErrors('start_date');
    }

    public function test_quest_takes_dates_the_same_way(): void
    {
        $start = Clock::todayLocal()->addDay();
        $end = $start->addDays(20);   // 21 kun

        $this->asTg()
            ->postJson('/api/quests', [
                'title' => 'Sanali missiya',
                'start_date' => $start->toDateString(),
                'end_date' => $end->toDateString(),
                'goal_percent' => 80,
                'challenges' => [[
                    'name' => 'Kitob',
                    'icon' => '📖',
                    'cadence' => 'daily',
                    'weekdays' => [],
                ]],
            ])
            ->assertStatus(200);

        $quest = Quest::firstOrFail();
        $this->assertSame(21, $quest->period_days);
        $this->assertSame($start->toDateString(), $quest->start_date->toDateString());

        // Challenge missiya bilan birga boshlanadi, bugundan emas
        $this->assertSame(
            $start->toDateString(),
            $quest->challenges()->firstOrFail()->start_date->toDateString(),
        );
    }

    public function test_date_range_days_are_inclusive(): void
    {
        $range = DateRange::fromStrings('2026-08-01', '2026-08-07');

        $this->assertSame(7, $range->days(), 'ikkala chekka ham sanaladi');
        $this->assertSame(1, DateRange::fromStrings('2026-08-01', '2026-08-01')->days());
    }
}
