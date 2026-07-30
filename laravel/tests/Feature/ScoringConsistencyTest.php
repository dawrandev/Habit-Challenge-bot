<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CompletionStatus;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Completion;
use App\Models\User;
use App\Support\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScoringConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const TG_A = 3001; // creator
    private const TG_B = 3002; // completing user

    private function asTg(int $telegramId): self
    {
        return $this->withHeader('X-Dev-Telegram-Id', (string) $telegramId);
    }

    /**
     * @param  array<int, array<string, mixed>>  $challenges
     * @return array{battle: Battle, userA: User, userB: User}
     */
    private function makeBattle(array $challenges): array
    {
        $this->asTg(self::TG_A)->postJson('/api/battles', [
            'title' => 'Scoring Battle',
            'period_days' => 14,
            'start_tomorrow' => false,
            'challenges' => $challenges,
        ])->assertStatus(200);

        $battle = Battle::firstOrFail();
        $userA = User::where('telegram_id', self::TG_A)->firstOrFail();

        $userB = User::create(['telegram_id' => self::TG_B, 'first_name' => 'Bob']);
        BattleParticipant::create([
            'battle_id' => $battle->id,
            'user_id' => $userB->id,
            'accepted' => true,
        ]);

        return ['battle' => $battle, 'userA' => $userA, 'userB' => $userB];
    }

    private function approvedToday(int $challengeId, int $userId): Completion
    {
        return Completion::create([
            'challenge_id' => $challengeId,
            'user_id' => $userId,
            'day' => Clock::todayLocal()->toDateString(),
            'status' => CompletionStatus::Approved,
            'file_id' => 'dev-file',
            'submitted_at' => now(),
            'resolved_at' => now(),
        ]);
    }

    // 1. Misses no longer reduce the total; total == sum of breakdown.

    public function test_missed_days_do_not_reduce_total_score(): void
    {
        ['battle' => $battle, 'userB' => $userB] = $this->makeBattle([[
            'name' => 'Pushups',
            'icon' => '💪',
            'cadence' => 'daily',
            'proof_type' => 'camera',
            'weekdays' => [],
        ]]);

        $challenge = $battle->challenges()->firstOrFail();

        // Backdate the challenge (and battle) to 4 days ago so there are earlier "missed" due days.
        $startFourDaysAgo = Clock::todayLocal()->subDays(4)->toDateString();
        $challenge->update(['start_date' => $startFourDaysAgo]);
        $battle->update(['start_date' => $startFourDaysAgo]);

        // Only ONE approved completion: today. The 4 earlier due days are misses.
        $this->approvedToday($challenge->id, $userB->id);

        $detail = $this->asTg(self::TG_B)->getJson("/api/battles/{$battle->id}");
        $detail->assertStatus(200);

        $playerB = collect($detail->json('players'))->firstWhere('user.id', $userB->id);
        $this->assertNotNull($playerB);

        $breakdown = $playerB['breakdown'][(string) $challenge->id] ?? null;
        $this->assertSame(1, $breakdown, 'breakdown must count the 1 approved completion');
        $this->assertEqualsWithDelta(
            1,
            $playerB['score'],
            0.0001,
            'total score must equal collected points (1), misses must NOT reduce it',
        );

        // Total equals sum of the per-challenge breakdown.
        $this->assertEqualsWithDelta(
            array_sum($playerB['breakdown']),
            $playerB['score'],
            0.0001,
            'top total must equal the sum of the breakdown numbers',
        );
    }

    // 2. Multi-challenge total == sum of breakdown.

    public function test_multi_challenge_total_equals_sum_of_breakdown(): void
    {
        ['battle' => $battle, 'userB' => $userB] = $this->makeBattle([
            [
                'name' => 'Pushups',
                'icon' => '💪',
                'cadence' => 'daily',
                'proof_type' => 'camera',
                'weekdays' => [],
            ],
            [
                'name' => 'Reading',
                'icon' => '📚',
                'cadence' => 'daily',
                'proof_type' => 'screenshot',
                'weekdays' => [],
            ],
        ]);

        $challenges = $battle->challenges()->orderBy('id')->get();
        $c1 = $challenges[0];
        $c2 = $challenges[1];

        // 1 approved completion (today) in each challenge.
        $this->approvedToday($c1->id, $userB->id);
        $this->approvedToday($c2->id, $userB->id);

        $detail = $this->asTg(self::TG_B)->getJson("/api/battles/{$battle->id}");
        $detail->assertStatus(200);

        $playerB = collect($detail->json('players'))->firstWhere('user.id', $userB->id);
        $this->assertNotNull($playerB);

        $this->assertSame(1, $playerB['breakdown'][(string) $c1->id] ?? null);
        $this->assertSame(1, $playerB['breakdown'][(string) $c2->id] ?? null);
        $this->assertEqualsWithDelta(2, $playerB['score'], 0.0001, 'total must be 2');
        $this->assertEqualsWithDelta(
            array_sum($playerB['breakdown']),
            $playerB['score'],
            0.0001,
            'total must equal sum of breakdown (2)',
        );
    }
}
