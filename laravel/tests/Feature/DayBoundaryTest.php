<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Cadence;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Quest;
use App\Models\User;
use App\Services\CompletionService;
use App\Services\QuestStatsService;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kun chegarasi — "cho'zilgan kecha".
 *
 * Hayotiy holat: odam odatini kechqurun bajaradi, 23:59 da bitta isbot
 * yuborib ulguradi, qolganini yuborayotganda 00:00 bo'lib qoladi. Kalendar
 * bo'yicha bu keyingi kun, lekin odam uchun bu o'sha kechaning davomi —
 * uni bajargan ishi uchun jazolash noto'g'ri.
 *
 * Shuning uchun kun `day_start_hour` da almashadi (default 04:00).
 */
class DayBoundaryTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Tashkent';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** Vaqtni Toshkent bo'yicha qotiradi. */
    private function freezeAt(string $localTime): void
    {
        Carbon::setTestNow(CarbonImmutable::parse($localTime, self::TZ));
    }

    private function withCutoff(int $hour): void
    {
        config()->set('telegram.day_start_hour', $hour);
    }

    // --- Clock ---------------------------------------------------------------

    public function test_before_the_cutoff_it_is_still_yesterday(): void
    {
        $this->withCutoff(4);

        $this->freezeAt('2026-09-02 00:30');
        $this->assertSame(
            '2026-09-01',
            Clock::todayLocal()->toDateString(),
            '00:30 hali kechagi kun',
        );

        $this->freezeAt('2026-09-02 03:59');
        $this->assertSame('2026-09-01', Clock::todayLocal()->toDateString());
    }

    public function test_at_the_cutoff_the_day_flips(): void
    {
        $this->withCutoff(4);

        $this->freezeAt('2026-09-02 04:00');
        $this->assertSame('2026-09-02', Clock::todayLocal()->toDateString());

        $this->freezeAt('2026-09-02 23:59');
        $this->assertSame('2026-09-02', Clock::todayLocal()->toDateString());
    }

    public function test_zero_cutoff_keeps_the_plain_calendar_day(): void
    {
        // Sozlamani 0 ga qo'ysa — eski, qat'iy yarim tun xatti-harakati
        $this->withCutoff(0);

        $this->freezeAt('2026-09-02 00:30');
        $this->assertSame('2026-09-02', Clock::todayLocal()->toDateString());
        $this->assertFalse(Clock::inGraceWindow());
    }

    public function test_grace_window_is_reported_only_between_midnight_and_cutoff(): void
    {
        $this->withCutoff(4);

        $this->freezeAt('2026-09-02 01:00');
        $this->assertTrue(Clock::inGraceWindow());

        $this->freezeAt('2026-09-02 04:01');
        $this->assertFalse(Clock::inGraceWindow());

        $this->freezeAt('2026-09-02 22:00');
        $this->assertFalse(Clock::inGraceWindow());
    }

    public function test_day_ends_at_the_cutoff_of_the_next_morning(): void
    {
        $this->withCutoff(4);
        $this->freezeAt('2026-09-01 22:00');

        $this->assertSame(
            '2026-09-02 04:00',
            Clock::dayEndsAt()->format('Y-m-d H:i'),
            '1-sentabr kuni 2-sentabr 04:00 da tugaydi',
        );
    }

    // --- Asosiy stsenariy: 23:59 va 00:05 bir kunga tushadi ------------------

    public function test_proofs_sent_at_2359_and_0005_land_on_the_same_day(): void
    {
        $this->withCutoff(4);

        // 1-sentabrda boshlangan missiya, ikkita odat
        $this->freezeAt('2026-09-01 10:00');

        $owner = User::create(['telegram_id' => 7001, 'first_name' => 'Ega']);
        $quest = Quest::create([
            'title' => 'Kechqurungi odatlar',
            'status' => 'active',
            'owner_id' => $owner->id,
            'period_days' => 7,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-07',
            'timezone' => self::TZ,
            'goal_percent' => 80,
            'invite_token' => Str::random(12),
        ]);

        $challenges = collect(['Sport', 'Kitob'])->map(fn ($name) => Challenge::create([
            'quest_id' => $quest->id,
            'name' => $name,
            'icon' => '🏃',
            'cadence' => Cadence::Daily,
            'weekdays' => [],
            'start_date' => '2026-09-01',
            'pending' => false,
        ]));

        $service = app(CompletionService::class);

        // Birinchisi — 23:59
        $this->freezeAt('2026-09-01 23:59');
        $first = $service->submit($owner, $challenges[0]->id, 'bytes');

        // Ikkinchisi — yarim tundan keyin, lekin hali o'sha kechada
        $this->freezeAt('2026-09-02 00:05');
        $second = $service->submit($owner, $challenges[1]->id, 'bytes');

        $this->assertSame('2026-09-01', $first->day->toDateString());
        $this->assertSame(
            '2026-09-01',
            $second->day->toDateString(),
            '00:05 dagi isbot ham 1-sentabrga yozilishi kerak',
        );
    }

    public function test_after_the_cutoff_the_proof_belongs_to_the_new_day(): void
    {
        $this->withCutoff(4);
        $this->freezeAt('2026-09-01 10:00');

        $owner = User::create(['telegram_id' => 7002, 'first_name' => 'Ega']);
        $quest = Quest::create([
            'title' => 'Ertalabki',
            'status' => 'active',
            'owner_id' => $owner->id,
            'period_days' => 7,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-07',
            'timezone' => self::TZ,
            'goal_percent' => 80,
            'invite_token' => Str::random(12),
        ]);
        $challenge = Challenge::create([
            'quest_id' => $quest->id,
            'name' => 'Sport',
            'icon' => '🏃',
            'cadence' => Cadence::Daily,
            'weekdays' => [],
            'start_date' => '2026-09-01',
            'pending' => false,
        ]);

        // 04:30 — kun allaqachon almashgan
        $this->freezeAt('2026-09-02 04:30');
        $completion = app(CompletionService::class)->submit($owner, $challenge->id, 'bytes');

        $this->assertSame('2026-09-02', $completion->day->toDateString());
    }

    public function test_late_night_proof_is_not_counted_as_a_miss(): void
    {
        // Eng muhimi: 00:05 da yuborilgan isbot kechagi kunni O'TKAZIB
        // YUBORILGAN deb belgilamasligi kerak.
        $this->withCutoff(4);
        $this->freezeAt('2026-09-01 10:00');

        $owner = User::create(['telegram_id' => 7003, 'first_name' => 'Ega']);
        $quest = Quest::create([
            'title' => 'Kech',
            'status' => 'active',
            'owner_id' => $owner->id,
            'period_days' => 3,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'timezone' => self::TZ,
            'goal_percent' => 80,
            'invite_token' => Str::random(12),
        ]);
        $challenge = Challenge::create([
            'quest_id' => $quest->id,
            'name' => 'Sport',
            'icon' => '🏃',
            'cadence' => Cadence::Daily,
            'weekdays' => [],
            'start_date' => '2026-09-01',
            'pending' => false,
        ]);

        $this->freezeAt('2026-09-02 00:05');
        $completion = app(CompletionService::class)->submit($owner, $challenge->id, 'bytes');
        $completion->update(['status' => 'approved', 'resolved_at' => now()]);

        // Hali o'sha kun — 1-sentabr bajarilgan, o'tkazilgan kun yo'q
        $report = app(QuestStatsService::class)->report($quest->fresh());

        $this->assertSame(1, $report->done);
        $this->assertSame(0, $report->missed, '1-sentabr o`tkazilgan deb sanalmasligi kerak');
    }

    // --- API -----------------------------------------------------------------

    public function test_day_endpoint_tells_the_client_which_day_it_is(): void
    {
        $this->withCutoff(4);
        $this->freezeAt('2026-09-02 01:30');

        $this->getJson('/api/day')
            ->assertStatus(200)
            ->assertJsonPath('date', '2026-09-01')
            ->assertJsonPath('grace', true)
            ->assertJsonPath('day_start_hour', 4);
    }

    public function test_day_endpoint_reports_no_grace_during_normal_hours(): void
    {
        $this->withCutoff(4);
        $this->freezeAt('2026-09-02 14:00');

        $this->getJson('/api/day')
            ->assertStatus(200)
            ->assertJsonPath('date', '2026-09-02')
            ->assertJsonPath('grace', false);
    }

    public function test_today_tasks_still_show_yesterdays_list_before_the_cutoff(): void
    {
        $this->withCutoff(4);
        $this->freezeAt('2026-09-01 10:00');

        $owner = User::create(['telegram_id' => 7004, 'first_name' => 'Ega']);
        $quest = Quest::create([
            'title' => 'Ro`yxat',
            'status' => 'active',
            'owner_id' => $owner->id,
            'period_days' => 3,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'timezone' => self::TZ,
            'goal_percent' => 80,
            'invite_token' => Str::random(12),
        ]);
        Challenge::create([
            'quest_id' => $quest->id,
            'name' => 'Sport',
            'icon' => '🏃',
            'cadence' => Cadence::Daily,
            'weekdays' => [],
            'start_date' => '2026-09-01',
            'pending' => false,
        ]);

        // 00:30 — ro'yxat hali 1-sentabrniki va bo'sh emas
        $this->freezeAt('2026-09-02 00:30');

        $tasks = $this->withHeader('X-Dev-Telegram-Id', '7004')
            ->getJson("/api/quests/{$quest->id}/today")
            ->assertStatus(200)
            ->json();

        $this->assertCount(1, $tasks);
        $this->assertNull($tasks[0]['status'], 'hali bajarilmagan');

        // Shu payt yuborilgan isbot o'sha kunga tushadi
        $completion = app(CompletionService::class)->submit(
            $owner,
            $tasks[0]['challenge']['id'],
            'bytes',
        );
        $this->assertSame('2026-09-01', $completion->day->toDateString());
    }

    public function test_a_second_proof_the_same_night_replaces_rather_than_duplicates(): void
    {
        // (challenge_id, user_id, day) unique — cho'zilgan kechada qayta
        // yuborish yangi kun ochmasligi kerak
        $this->withCutoff(4);
        $this->freezeAt('2026-09-01 10:00');

        $owner = User::create(['telegram_id' => 7005, 'first_name' => 'Ega']);
        $quest = Quest::create([
            'title' => 'Qayta',
            'status' => 'active',
            'owner_id' => $owner->id,
            'period_days' => 3,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-03',
            'timezone' => self::TZ,
            'goal_percent' => 80,
            'invite_token' => Str::random(12),
        ]);
        $challenge = Challenge::create([
            'quest_id' => $quest->id,
            'name' => 'Sport',
            'icon' => '🏃',
            'cadence' => Cadence::Daily,
            'weekdays' => [],
            'start_date' => '2026-09-01',
            'pending' => false,
        ]);

        $service = app(CompletionService::class);

        $this->freezeAt('2026-09-01 23:50');
        $service->submit($owner, $challenge->id, 'birinchi');

        $this->freezeAt('2026-09-02 02:00');
        $service->submit($owner, $challenge->id, 'ikkinchi');

        $this->assertSame(1, Completion::count(), 'bitta yozuv qolishi kerak');
        $this->assertSame('2026-09-01', Completion::first()->day->toDateString());
    }
}
