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
 * Eski ma'lumotni tuzatish buyrug'i.
 *
 * Bu PRODUCTION ma'lumotiga tegadi, shuning uchun asosiy talab — hech narsani
 * so'ramasdan o'zgartirmaslik va ikki marta yurganda buzmaslik.
 */
class FixLateNightDaysTest extends TestCase
{
    use RefreshDatabase;

    private const TZ = 'Asia/Tashkent';

    private Challenge $challenge;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('telegram.day_start_hour', 4);

        $this->owner = User::create(['telegram_id' => 8001, 'first_name' => 'Ega']);

        $quest = Quest::create([
            'title' => 'Tuzatish',
            'status' => 'active',
            'owner_id' => $this->owner->id,
            'period_days' => 7,
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-07',
            'timezone' => self::TZ,
            'goal_percent' => 80,
            'invite_token' => Str::random(12),
        ]);

        $this->challenge = Challenge::create([
            'quest_id' => $quest->id,
            'name' => 'Sport',
            'icon' => '🏃',
            'cadence' => Cadence::Daily,
            'weekdays' => [],
            'start_date' => '2026-09-01',
            'pending' => false,
        ]);
    }

    /** Eski qoida bilan yozilgan isbot: 00:05 da yuborilgan, 2-sentabrga yozilgan. */
    private function lateNight(string $day, string $submittedLocal): Completion
    {
        return Completion::create([
            'challenge_id' => $this->challenge->id,
            'user_id' => $this->owner->id,
            'day' => $day,
            'status' => CompletionStatus::Approved,
            'file_id' => 'x',
            'submitted_at' => CarbonImmutable::parse($submittedLocal, self::TZ)->utc(),
            'resolved_at' => now(),
        ]);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $c = $this->lateNight('2026-09-02', '2026-09-02 00:05');

        $this->artisan('battle:fix-late-nights')->assertExitCode(0);

        $this->assertSame(
            '2026-09-02',
            $c->fresh()->day->toDateString(),
            '--apply siz hech narsa o`zgarmasligi kerak',
        );
    }

    public function test_apply_moves_the_proof_back_one_day(): void
    {
        $c = $this->lateNight('2026-09-02', '2026-09-02 00:05');

        $this->artisan('battle:fix-late-nights --apply')->assertExitCode(0);

        $this->assertSame('2026-09-01', $c->fresh()->day->toDateString());
    }

    public function test_proofs_sent_after_the_cutoff_are_left_alone(): void
    {
        $c = $this->lateNight('2026-09-02', '2026-09-02 09:30');

        $this->artisan('battle:fix-late-nights --apply')->assertExitCode(0);

        $this->assertSame('2026-09-02', $c->fresh()->day->toDateString(), 'kunduzgi isbotga tegilmaydi');
    }

    public function test_running_twice_is_safe(): void
    {
        $c = $this->lateNight('2026-09-02', '2026-09-02 00:05');

        $this->artisan('battle:fix-late-nights --apply')->assertExitCode(0);
        $this->artisan('battle:fix-late-nights --apply')->assertExitCode(0);

        $this->assertSame(
            '2026-09-01',
            $c->fresh()->day->toDateString(),
            'ikkinchi yurish yana bir kun orqaga surmasligi kerak',
        );
    }

    public function test_collision_with_an_existing_day_is_skipped(): void
    {
        // 1-sentabr allaqachon band — ko'chirish unique cheklovni buzardi
        Completion::create([
            'challenge_id' => $this->challenge->id,
            'user_id' => $this->owner->id,
            'day' => '2026-09-01',
            'status' => CompletionStatus::Approved,
            'file_id' => 'oldingi',
            'submitted_at' => CarbonImmutable::parse('2026-09-01 20:00', self::TZ)->utc(),
            'resolved_at' => now(),
        ]);

        $late = $this->lateNight('2026-09-02', '2026-09-02 00:05');

        $this->artisan('battle:fix-late-nights --apply')->assertExitCode(0);

        $this->assertSame('2026-09-02', $late->fresh()->day->toDateString(), 'band kunga ko`chirilmaydi');
        $this->assertSame(2, Completion::count(), 'yozuvlar yo`qolmaydi');
    }

    public function test_proof_before_the_period_start_is_skipped(): void
    {
        // 1-sentabrga yozilgan, 00:05 da yuborilgan → 31-avgustga ko'chirilardi,
        // lekin davr 1-sentabrda boshlangan
        $c = $this->lateNight('2026-09-01', '2026-09-01 00:05');

        $this->artisan('battle:fix-late-nights --apply')->assertExitCode(0);

        $this->assertSame('2026-09-01', $c->fresh()->day->toDateString());
    }

    public function test_missed_rows_are_never_touched(): void
    {
        $missed = Completion::create([
            'challenge_id' => $this->challenge->id,
            'user_id' => $this->owner->id,
            'day' => '2026-09-02',
            'status' => CompletionStatus::Missed,
            'submitted_at' => null,
        ]);

        $this->artisan('battle:fix-late-nights --apply')->assertExitCode(0);

        $this->assertSame('2026-09-02', $missed->fresh()->day->toDateString());
    }

    public function test_can_target_a_single_user(): void
    {
        $other = User::create(['telegram_id' => 8002, 'first_name' => 'Boshqa']);
        $mine = $this->lateNight('2026-09-02', '2026-09-02 00:05');

        $theirs = Completion::create([
            'challenge_id' => $this->challenge->id,
            'user_id' => $other->id,
            'day' => '2026-09-02',
            'status' => CompletionStatus::Approved,
            'file_id' => 'y',
            'submitted_at' => CarbonImmutable::parse('2026-09-02 00:10', self::TZ)->utc(),
            'resolved_at' => now(),
        ]);

        $this->artisan('battle:fix-late-nights --apply --telegram-id=8001')->assertExitCode(0);

        $this->assertSame('2026-09-01', $mine->fresh()->day->toDateString());
        $this->assertSame('2026-09-02', $theirs->fresh()->day->toDateString(), 'boshqa odamga tegilmaydi');
    }
}
