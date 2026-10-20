<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BattleStatus;
use App\Enums\Cadence;
use App\Enums\CompletionStatus;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\User;
use App\Services\BattleClosingService;
use App\Services\CompletionService;
use App\Support\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Tests\TestCase;

/**
 * Kechagi hisobot — SPEC §18.
 *
 * Odatni bajarib, isbot yuborishni unutgan odam keyingi kuni ham yubora oladi
 * va jarima (−0.5) bajarishga (+1) aylanadi. Faqat bir kun orqaga.
 */
class LateProofTest extends TestCase
{
    use RefreshDatabase;

    private const TG_ME = 7001;

    private const TG_RIVAL = 7002;

    private User $me;

    private User $rival;

    private function asTg(int $telegramId): self
    {
        return $this->withHeader('X-Dev-Telegram-Id', (string) $telegramId);
    }

    /**
     * @return array{battle: Battle, challenge: Challenge}
     */
    private function battle(int $startDaysAgo, int $endInDays, array $challenge = []): array
    {
        $this->me = User::firstOrCreate(['telegram_id' => self::TG_ME], ['first_name' => 'Men']);
        $this->rival = User::firstOrCreate(['telegram_id' => self::TG_RIVAL], ['first_name' => 'Raqib']);
        $today = Clock::todayLocal();

        $battle = Battle::create([
            'title' => 'Duel',
            'status' => BattleStatus::Active,
            'period_days' => $startDaysAgo + $endInDays + 1,
            'start_date' => $today->subDays($startDaysAgo)->toDateString(),
            'end_date' => $today->addDays($endInDays)->toDateString(),
            'timezone' => config('telegram.timezone'),
            'created_by' => $this->me->id,
            'invite_token' => Str::random(12),
        ]);
        BattleParticipant::create(['battle_id' => $battle->id, 'user_id' => $this->me->id, 'accepted' => true]);
        BattleParticipant::create(['battle_id' => $battle->id, 'user_id' => $this->rival->id, 'accepted' => true]);

        $challenge = Challenge::create(array_merge([
            'battle_id' => $battle->id,
            'name' => 'Sport',
            'icon' => '🏃',
            'cadence' => Cadence::Daily,
            'weekdays' => [],
            'start_date' => $battle->start_date->toDateString(),
            'pending' => false,
        ], $challenge));

        return ['battle' => $battle, 'challenge' => $challenge];
    }

    private function submit(Challenge $challenge, ?string $day): Completion
    {
        return app(CompletionService::class)->submit($this->me, $challenge->id, 'bytes', day: $day);
    }

    private function yesterday(): string
    {
        return Clock::todayLocal()->subDay()->toDateString();
    }

    public function test_forgotten_day_can_be_sent_next_day_and_turns_penalty_into_points(): void
    {
        ['battle' => $battle, 'challenge' => $challenge] = $this->battle(1, 5);

        // Kecha yopildi — o'tkazib yuborilgan deb belgilandi, ball −0.5
        app(BattleClosingService::class)->markMissed();
        $myScore = fn () => collect($this->asTg(self::TG_ME)->getJson("/api/battles/{$battle->id}")->json('players'))
            ->firstWhere('is_me', true)['score'];
        $this->assertEquals(-0.5, $myScore());

        $completion = $this->submit($challenge, $this->yesterday());
        $this->assertSame(CompletionStatus::Pending, $completion->status);
        $this->assertSame($this->yesterday(), $completion->day->toDateString());

        $this->asTg(self::TG_RIVAL)
            ->postJson("/api/completions/{$completion->id}/verify", ['approve' => true])
            ->assertStatus(200);

        $this->assertEquals(1.0, $myScore(), 'jarima o\'rniga +1');
    }

    public function test_late_proof_goes_through_the_api(): void
    {
        ['challenge' => $challenge] = $this->battle(1, 5);

        $this->asTg(self::TG_ME)
            ->post('/api/completions', [
                'challenge_id' => $challenge->id,
                'file' => UploadedFile::fake()->image('p.jpg'),
                'day' => $this->yesterday(),
            ], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $this->assertDatabaseHas('completions', [
            'challenge_id' => $challenge->id,
            'user_id' => $this->me->id,
            'status' => CompletionStatus::Pending->value,
        ]);
    }

    public function test_two_days_back_is_too_late(): void
    {
        ['challenge' => $challenge] = $this->battle(3, 5);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->submit($challenge, Clock::todayLocal()->subDays(2)->toDateString());
    }

    public function test_already_sent_day_cannot_be_overwritten(): void
    {
        ['challenge' => $challenge] = $this->battle(1, 5);
        Completion::create([
            'challenge_id' => $challenge->id,
            'user_id' => $this->me->id,
            'day' => $this->yesterday(),
            'status' => CompletionStatus::Approved,
            'file_id' => 'x',
        ]);

        $this->expectException(ConflictHttpException::class);
        $this->submit($challenge, $this->yesterday());
    }

    public function test_rejected_yesterday_can_be_resent(): void
    {
        ['challenge' => $challenge] = $this->battle(1, 5);
        Completion::create([
            'challenge_id' => $challenge->id,
            'user_id' => $this->me->id,
            'day' => $this->yesterday(),
            'status' => CompletionStatus::Rejected,
            'file_id' => 'x',
        ]);

        $this->assertSame(CompletionStatus::Pending, $this->submit($challenge, $this->yesterday())->status);
    }

    public function test_day_the_habit_was_not_due_is_refused(): void
    {
        $yesterdayWeekday = (Clock::todayLocal()->subDay()->dayOfWeekIso - 1);
        $otherDay = ($yesterdayWeekday + 3) % 7;
        ['challenge' => $challenge] = $this->battle(1, 5, [
            'cadence' => Cadence::WeeklyDays,
            'weekdays' => [$otherDay],
        ]);

        $this->expectException(UnprocessableEntityHttpException::class);
        $this->submit($challenge, $this->yesterday());
    }

    public function test_day_before_battle_start_is_refused(): void
    {
        ['challenge' => $challenge] = $this->battle(0, 5);

        // Odat ham battle bilan bugun boshlangan — kecha u hali yo'q edi
        $this->expectException(UnprocessableEntityHttpException::class);
        $this->submit($challenge, $this->yesterday());
    }

    public function test_late_tasks_lists_only_unsent_days(): void
    {
        ['battle' => $battle, 'challenge' => $sport] = $this->battle(1, 5);
        $read = Challenge::create([
            'battle_id' => $battle->id, 'name' => 'Kitob', 'icon' => '📖',
            'cadence' => Cadence::Daily, 'weekdays' => [],
            'start_date' => $battle->start_date->toDateString(), 'pending' => false,
        ]);
        Completion::create([
            'challenge_id' => $read->id, 'user_id' => $this->me->id,
            'day' => $this->yesterday(), 'status' => CompletionStatus::Pending, 'file_id' => 'x',
        ]);

        $this->asTg(self::TG_ME)->getJson('/api/late-tasks')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.challenge.id', $sport->id)
            ->assertJsonPath('0.day', $this->yesterday())
            ->assertJsonPath('0.context.key', 'battle')
            ->assertJsonPath('0.context.id', $battle->id);
    }

    public function test_battle_stays_open_one_extra_day_for_the_last_days_proof(): void
    {
        // Oxirgi kun — kecha
        ['battle' => $battle, 'challenge' => $challenge] = $this->battle(3, -1);

        app(BattleClosingService::class)->runDailyClose();
        $this->assertSame(BattleStatus::Active, $battle->fresh()->status, 'kechagilar kuni hali yopilmaydi');

        // Bugun davrdan tashqarida — bugungi vazifa yo'q, lekin kechagi bor
        $this->asTg(self::TG_ME)->getJson("/api/battles/{$battle->id}/today")->assertExactJson([]);
        $this->asTg(self::TG_ME)->getJson('/api/late-tasks')->assertJsonCount(1);

        $late = $this->submit($challenge, $this->yesterday());

        // Ertasiga yopiladi — tekshirilmagan isbot ham hisobga kiradi
        $this->travel(1)->days();
        app(BattleClosingService::class)->runDailyClose();

        $this->assertSame(BattleStatus::Finished, $battle->fresh()->status);
        $this->assertSame(CompletionStatus::AutoApproved, $late->fresh()->status);
    }

    public function test_today_is_still_sent_without_a_day(): void
    {
        ['challenge' => $challenge] = $this->battle(1, 5);

        $this->assertSame(
            Clock::todayLocal()->toDateString(),
            $this->submit($challenge, null)->day->toDateString(),
        );
    }
}
