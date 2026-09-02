<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\Cadence;
use App\Enums\CompletionStatus;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Quest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Kun hisoboti — diagnostika buyrug'i.
 *
 * Asosiy talab: FAQAT O'QIYDI. Bu buyruq production'da, muammoni tekshirish
 * paytida ishlatiladi — u ma'lumotni o'zgartirsa ishonch yo'qolardi.
 */
class DayReportTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Tashkent';

    private User $user;

    private Quest $quest;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telegram.day_start_hour', 4);

        $this->user = User::create(['telegram_id' => 9001, 'first_name' => 'Ega']);

        $this->quest = Quest::create([
            'title' => 'Hisobot',
            'status' => 'active',
            'owner_id' => $this->user->id,
            'period_days' => 7,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-07',
            'timezone' => self::TZ,
            'goal_percent' => 80,
            'invite_token' => Str::random(12),
        ]);
    }

    private function challenge(string $name): Challenge
    {
        return Challenge::create([
            'quest_id' => $this->quest->id,
            'name' => $name,
            'icon' => '🏃',
            'cadence' => Cadence::Daily,
            'weekdays' => [],
            'start_date' => '2026-09-01',
            'pending' => false,
        ]);
    }

    public function test_it_never_modifies_anything(): void
    {
        $a = $this->challenge('Sport');
        $completion = Completion::create([
            'challenge_id' => $a->id,
            'user_id' => $this->user->id,
            'day' => '2026-09-02',
            'status' => CompletionStatus::Approved,
            'file_id' => 'x',
            'submitted_at' => CarbonImmutable::parse('2026-09-02 00:05', self::TZ)->utc(),
            'resolved_at' => now(),
        ]);

        $before = $completion->fresh()->toArray();
        $count = Completion::count();

        $this->artisan('battle:day-report --telegram-id=9001 --date=2026-09-01')
            ->assertExitCode(0);

        $this->assertSame($before, $completion->fresh()->toArray(), 'yozuv o`zgarmasligi kerak');
        $this->assertSame($count, Completion::count());
    }

    public function test_it_lists_expected_habits_and_flags_the_missing_ones(): void
    {
        $this->challenge('Sport');
        $this->challenge('Kitob');

        $this->artisan('battle:day-report --telegram-id=9001 --date=2026-09-01')
            ->expectsOutputToContain('YUBORILMAGAN')
            ->assertExitCode(0);
    }

    public function test_it_surfaces_a_proof_that_landed_on_another_day(): void
    {
        // Aynan foydalanuvchining holati: 00:05 da yuborilgan, keyingi kunga yozilgan
        $a = $this->challenge('Instagram');
        Completion::create([
            'challenge_id' => $a->id,
            'user_id' => $this->user->id,
            'day' => '2026-09-02',
            'status' => CompletionStatus::Approved,
            'file_id' => 'x',
            'submitted_at' => CarbonImmutable::parse('2026-09-02 00:05', self::TZ)->utc(),
            'resolved_at' => now(),
        ]);

        $this->artisan('battle:day-report --telegram-id=9001 --date=2026-09-01')
            ->expectsOutputToContain('BOSHQA kunga yozilgan')
            ->assertExitCode(0);
    }

    public function test_unknown_user_fails_cleanly(): void
    {
        $this->artisan('battle:day-report --telegram-id=999999')
            ->assertExitCode(1);
    }
}
