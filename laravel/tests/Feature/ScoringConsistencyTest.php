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

    private function singleDaily(): array
    {
        return [[
            'name' => 'Pushups',
            'icon' => '💪',
            'cadence' => 'daily',
            'proof_type' => 'camera',
            'weekdays' => [],
        ]];
    }

    // 1. Misses reduce the total, with NO floor (score can be negative).

    public function test_missed_days_reduce_total_with_no_floor(): void
    {
        ['battle' => $battle, 'userB' => $userB] = $this->makeBattle($this->singleDaily());
        $challenge = $battle->challenges()->firstOrFail();

        // Backdate challenge + battle to 4 days ago -> 5 due days (day -4..0).
        $startFourDaysAgo = Clock::todayLocal()->subDays(4)->toDateString();
        $challenge->update(['start_date' => $startFourDaysAgo]);
        $battle->update(['start_date' => $startFourDaysAgo]);

        // Only ONE approved completion: today. The 4 earlier due days are misses.
        $this->approvedToday($challenge->id, $userB->id);

        $playerB = $this->playerB($battle, $userB->id);

        // Breakdown (approved-count) is unchanged: still 1, non-negative.
        $this->assertSame(1, $playerB['breakdown'][(string) $challenge->id] ?? null);

        // Total = +1 (today approved) - 4*0.5 (missed past days) = -1.0, NOT floored.
        $this->assertEqualsWithDelta(
            -1.0,
            $playerB['score'],
            0.0001,
            'misses must reduce the total below zero (no floor): 1 - 4*0.5 = -1.0',
        );
    }

    // 2. Today's un-submitted day is NOT penalized (only strictly-past days).

    public function test_today_unsubmitted_day_is_not_penalized(): void
    {
        ['battle' => $battle, 'userB' => $userB] = $this->makeBattle($this->singleDaily());
        $challenge = $battle->challenges()->firstOrFail();

        // Started 1 day ago -> yesterday (missed) + today (not yet penalized). No completions.
        $startYesterday = Clock::todayLocal()->subDays(1)->toDateString();
        $challenge->update(['start_date' => $startYesterday]);
        $battle->update(['start_date' => $startYesterday]);

        $playerB = $this->playerB($battle, $userB->id);

        $this->assertSame(0, $playerB['breakdown'][(string) $challenge->id] ?? null);
        // yesterday missed = -0.5; today not penalized (day not over).
        $this->assertEqualsWithDelta(
            -0.5,
            $playerB['score'],
            0.0001,
            'only yesterday is penalized; today is not yet over: -0.5',
        );
    }

    // 3. Approved today with no past misses = +1, and the completing user leads.

    public function test_approved_today_no_past_misses_scores_plus_one(): void
    {
        ['battle' => $battle, 'userA' => $userA, 'userB' => $userB] = $this->makeBattle($this->singleDaily());
        $challenge = $battle->challenges()->firstOrFail();
        // Challenge starts today (default from create with start_tomorrow=false).

        $this->approvedToday($challenge->id, $userB->id);

        $detail = $this->asTg(self::TG_B)->getJson("/api/battles/{$battle->id}");
        $detail->assertStatus(200);
        $players = collect($detail->json('players'));

        $playerB = $players->firstWhere('user.id', $userB->id);
        $playerA = $players->firstWhere('user.id', $userA->id);

        $this->assertSame(1, $playerB['breakdown'][(string) $challenge->id] ?? null);
        $this->assertEqualsWithDelta(1.0, $playerB['score'], 0.0001, 'approved today, no misses: +1.0');
        // A did nothing, but today is not over -> no penalty -> 0.
        $this->assertEqualsWithDelta(0.0, $playerA['score'], 0.0001);
        $this->assertGreaterThan($playerA['score'], $playerB['score'], 'completing user leads');
    }

    // 4. Multi-challenge: sum of (+1 approved / -0.5 past miss) across challenges, no floor.

    public function test_multi_challenge_sum_with_penalties_no_floor(): void
    {
        ['battle' => $battle, 'userB' => $userB] = $this->makeBattle([
            ['name' => 'Pushups', 'icon' => '💪', 'cadence' => 'daily', 'proof_type' => 'camera', 'weekdays' => []],
            ['name' => 'Reading', 'icon' => '📚', 'cadence' => 'daily', 'proof_type' => 'screenshot', 'weekdays' => []],
        ]);

        $challenges = $battle->challenges()->orderBy('id')->get();
        $c1 = $challenges[0];
        $c2 = $challenges[1];

        // Backdate both challenges + battle to 2 days ago -> due days: day -2, -1, 0.
        $startTwoDaysAgo = Clock::todayLocal()->subDays(2)->toDateString();
        $c1->update(['start_date' => $startTwoDaysAgo]);
        $c2->update(['start_date' => $startTwoDaysAgo]);
        $battle->update(['start_date' => $startTwoDaysAgo]);

        // c1: approved today only -> +1 - 2*0.5 = 0.0 ; breakdown 1
        $this->approvedToday($c1->id, $userB->id);
        // c2: no completions -> 0 - 2*0.5 = -1.0 ; breakdown 0
        // (2 past misses: day -2 and day -1)

        $playerB = $this->playerB($battle, $userB->id);

        $this->assertSame(1, $playerB['breakdown'][(string) $c1->id] ?? null);
        $this->assertSame(0, $playerB['breakdown'][(string) $c2->id] ?? null);

        // Total = c1(0.0) + c2(-1.0) = -1.0, no floor.
        $this->assertEqualsWithDelta(
            -1.0,
            $playerB['score'],
            0.0001,
            'sum across challenges of (+1 approved / -0.5 past miss), no floor: 0.0 + (-1.0) = -1.0',
        );
    }

    private function approvedOn(int $challengeId, int $userId, string $day): Completion
    {
        return Completion::create([
            'challenge_id' => $challengeId,
            'user_id' => $userId,
            'day' => $day,
            'status' => CompletionStatus::Approved,
            'file_id' => 'dev-file',
            'submitted_at' => now(),
            'resolved_at' => now(),
        ]);
    }

    // 5. Pre-battle days are NOT penalized (effective start = max(challenge, battle) start).

    public function test_pre_battle_days_are_not_penalized(): void
    {
        ['battle' => $battle, 'userB' => $userB] = $this->makeBattle($this->singleDaily());
        $challenge = $battle->challenges()->firstOrFail();

        $yesterday = Clock::todayLocal()->subDays(1)->toDateString();  // battle start
        $dayBeforeStart = Clock::todayLocal()->subDays(2)->toDateString(); // challenge start (pre-battle)

        // Battle started yesterday; challenge start_date is one day BEFORE the battle.
        $battle->update(['start_date' => $yesterday]);
        $challenge->update(['start_date' => $dayBeforeStart]);

        // One approved completion on the battle's first day (yesterday).
        $this->approvedOn($challenge->id, $userB->id, $yesterday);

        $playerB = $this->playerB($battle, $userB->id);

        // Effective start = battle start (yesterday). Due days: yesterday (approved +1), today (not over, 0).
        // The pre-battle day (day-2) must NOT be counted as a miss.
        $this->assertSame(1, $playerB['breakdown'][(string) $challenge->id] ?? null);
        $this->assertEqualsWithDelta(
            1.0,
            $playerB['score'],
            0.0001,
            'pre-battle day must not be penalized: score is +1.0, not 0.5',
        );
    }

    // 6. Reported case: 4 challenges each with one approval on battle's first day -> total 4.0.

    public function test_reported_multi_challenge_pre_battle_bug(): void
    {
        ['battle' => $battle, 'userB' => $userB] = $this->makeBattle([
            ['name' => 'C1', 'icon' => '💪', 'cadence' => 'daily', 'proof_type' => 'camera', 'weekdays' => []],
            ['name' => 'C2', 'icon' => '📚', 'cadence' => 'daily', 'proof_type' => 'screenshot', 'weekdays' => []],
            ['name' => 'C3', 'icon' => '🏃', 'cadence' => 'daily', 'proof_type' => 'camera', 'weekdays' => []],
            ['name' => 'C4', 'icon' => '🧘', 'cadence' => 'daily', 'proof_type' => 'camera', 'weekdays' => []],
            ['name' => 'C5', 'icon' => '🎯', 'cadence' => 'daily', 'proof_type' => 'camera', 'weekdays' => []],
        ]);

        $yesterday = Clock::todayLocal()->subDays(1)->toDateString();      // battle start
        $dayBeforeStart = Clock::todayLocal()->subDays(2)->toDateString(); // challenge start (pre-battle)
        $battle->update(['start_date' => $yesterday]);

        $challenges = $battle->challenges()->orderBy('id')->get();
        // First 4 challenges: start pre-battle, each with an approval on the battle's first day.
        foreach ($challenges->take(4) as $challenge) {
            $challenge->update(['start_date' => $dayBeforeStart]);
            $this->approvedOn($challenge->id, $userB->id, $yesterday);
        }
        // 5th challenge: starts today, no completion.
        $c5 = $challenges[4];
        $c5->update(['start_date' => Clock::todayLocal()->toDateString()]);

        $playerB = $this->playerB($battle, $userB->id);

        foreach ($challenges->take(4) as $challenge) {
            $this->assertSame(1, $playerB['breakdown'][(string) $challenge->id] ?? null);
        }
        $this->assertSame(0, $playerB['breakdown'][(string) $c5->id] ?? null);

        // Each of the 4 contributes +1 (no pre-battle penalty, today not over); 5th contributes 0.
        $this->assertEqualsWithDelta(
            4.0,
            $playerB['score'],
            0.0001,
            'reported bug: should be 4.0 (was 2.0 when pre-battle days were penalized)',
        );
    }

    // 7. A challenge that legitimately starts AFTER battle start uses its own (later) start.

    public function test_challenge_starting_after_battle_uses_its_own_start(): void
    {
        ['battle' => $battle, 'userB' => $userB] = $this->makeBattle($this->singleDaily());
        $challenge = $battle->challenges()->firstOrFail();

        // Battle started 3 days ago; challenge added today (starts today).
        $battle->update(['start_date' => Clock::todayLocal()->subDays(3)->toDateString()]);
        $challenge->update(['start_date' => Clock::todayLocal()->toDateString()]);

        // No completion. Effective start = challenge start (today, the later date).
        // Only due-day is today, which is not over -> no penalty for the 3 earlier battle days.
        $playerB = $this->playerB($battle, $userB->id);

        $this->assertSame(0, $playerB['breakdown'][(string) $challenge->id] ?? null);
        $this->assertEqualsWithDelta(
            0.0,
            $playerB['score'],
            0.0001,
            'challenge starting today is not retroactively penalized for pre-existence battle days',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function playerB(Battle $battle, int $userBId): array
    {
        $detail = $this->asTg(self::TG_B)->getJson("/api/battles/{$battle->id}");
        $detail->assertStatus(200);
        $playerB = collect($detail->json('players'))->firstWhere('user.id', $userBId);
        $this->assertNotNull($playerB);

        return $playerB;
    }
}
