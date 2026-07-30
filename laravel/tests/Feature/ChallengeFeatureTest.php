<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CompletionStatus;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\User;
use App\Support\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChallengeFeatureTest extends TestCase
{
    use RefreshDatabase;

    private const TG_A = 1001; // battle creator / participant A
    private const TG_B = 1002; // second participant B

    /**
     * Authenticate a request as the given Telegram id (dev auth).
     */
    private function asTg(int $telegramId): self
    {
        return $this->withHeader('X-Dev-Telegram-Id', (string) $telegramId);
    }

    /**
     * Create a 2-person battle. User A (creator) via API, then B inserted as accepted participant.
     * The battle has one initial (non-pending) challenge.
     *
     * @param  array<string, mixed>  $challengeOverrides
     * @return array{battle: Battle, challenge: Challenge, userB: User}
     */
    private function makeBattle(array $challengeOverrides = []): array
    {
        $challenge = array_merge([
            'name' => 'Pushups',
            'description' => 'do 50 pushups',
            'icon' => '💪',
            'cadence' => 'daily',
            'proof_type' => 'camera',
            'weekdays' => [],
        ], $challengeOverrides);

        $response = $this->asTg(self::TG_A)->postJson('/api/battles', [
            'title' => 'Test Battle',
            'period_days' => 7,
            'start_tomorrow' => false,
            'challenges' => [$challenge],
        ]);
        $response->assertStatus(200);

        $battle = Battle::firstOrFail();

        // Create user B and make them an accepted participant.
        $userB = User::create(['telegram_id' => self::TG_B, 'first_name' => 'Bob']);
        BattleParticipant::create([
            'battle_id' => $battle->id,
            'user_id' => $userB->id,
            'accepted' => true,
        ]);

        return [
            'battle' => $battle,
            'challenge' => $battle->challenges()->firstOrFail(),
            'userB' => $userB,
        ];
    }

    // 1. updateChallenge changes proof_type (the reported bug) + description + preserves when omitted.

    public function test_update_challenge_changes_proof_type(): void
    {
        ['battle' => $battle, 'challenge' => $challenge] = $this->makeBattle([
            'proof_type' => 'camera',
        ]);
        $this->assertSame('camera', $challenge->proof_type->value);

        $response = $this->asTg(self::TG_A)->patchJson(
            "/api/battles/{$battle->id}/challenges/{$challenge->id}",
            [
                'name' => $challenge->name,
                'cadence' => 'daily', // required by AddChallengeRequest
                'proof_type' => 'screenshot',
            ],
        );
        $response->assertStatus(200);

        $this->assertSame('screenshot', $challenge->fresh()->proof_type->value);
    }

    public function test_update_challenge_changes_description(): void
    {
        ['battle' => $battle, 'challenge' => $challenge] = $this->makeBattle();

        $this->asTg(self::TG_A)->patchJson(
            "/api/battles/{$battle->id}/challenges/{$challenge->id}",
            [
                'name' => $challenge->name,
                'cadence' => 'daily',
                'description' => 'updated description',
            ],
        )->assertStatus(200);

        $this->assertSame('updated description', $challenge->fresh()->description);
    }

    public function test_update_challenge_without_proof_type_keeps_existing(): void
    {
        ['battle' => $battle, 'challenge' => $challenge] = $this->makeBattle([
            'proof_type' => 'screenshot',
        ]);

        $this->asTg(self::TG_A)->patchJson(
            "/api/battles/{$battle->id}/challenges/{$challenge->id}",
            [
                'name' => 'renamed only',
                'cadence' => 'daily',
                // proof_type intentionally omitted
            ],
        )->assertStatus(200);

        $fresh = $challenge->fresh();
        $this->assertSame('renamed only', $fresh->name);
        $this->assertSame('screenshot', $fresh->proof_type->value, 'proof_type must be preserved when omitted');
    }

    // 2. Proposal creates a pending challenge, excluded from scoring/breakdown.

    public function test_propose_creates_pending_challenge_excluded_from_breakdown(): void
    {
        ['battle' => $battle] = $this->makeBattle();
        $userA = User::where('telegram_id', self::TG_A)->firstOrFail();

        $response = $this->asTg(self::TG_A)->postJson(
            "/api/battles/{$battle->id}/challenges",
            [
                'name' => 'Reading',
                'description' => 'read 20 pages',
                'icon' => '📚',
                'cadence' => 'daily',
                'proof_type' => 'screenshot',
                'weekdays' => [],
            ],
        );
        // addChallenge returns a freshly-created model -> Laravel responds 201.
        $response->assertStatus(201);

        $proposed = Challenge::where('name', 'Reading')->firstOrFail();
        $this->assertTrue($proposed->pending, 'proposed challenge must be pending');
        $this->assertSame($userA->id, $proposed->proposed_by);

        // Battle detail: pending challenge must NOT appear in any player's breakdown.
        $detail = $this->asTg(self::TG_A)->getJson("/api/battles/{$battle->id}");
        $detail->assertStatus(200);

        foreach ($detail->json('players') as $player) {
            $this->assertArrayNotHasKey(
                (string) $proposed->id,
                $player['breakdown'] ?? [],
                'pending challenge must be excluded from scoring breakdown',
            );
        }
    }

    // 3. Accept (by the other participant) + self-accept blocked.

    public function test_other_participant_can_accept_pending_challenge(): void
    {
        ['battle' => $battle] = $this->makeBattle();

        // A proposes.
        $this->asTg(self::TG_A)->postJson("/api/battles/{$battle->id}/challenges", [
            'name' => 'Running',
            'cadence' => 'daily',
            'weekdays' => [],
        ])->assertStatus(201);
        $proposed = Challenge::where('name', 'Running')->firstOrFail();

        // B accepts.
        $this->asTg(self::TG_B)->postJson(
            "/api/battles/{$battle->id}/challenges/{$proposed->id}/accept",
        )->assertStatus(200);

        $fresh = $proposed->fresh();
        $this->assertFalse($fresh->pending, 'accepted challenge must no longer be pending');
        $this->assertSame(
            Clock::todayLocal()->toDateString(),
            $fresh->start_date->toDateString(),
            'accept must set start_date to today',
        );
    }

    public function test_proposer_cannot_self_accept(): void
    {
        ['battle' => $battle] = $this->makeBattle();

        $this->asTg(self::TG_A)->postJson("/api/battles/{$battle->id}/challenges", [
            'name' => 'Yoga',
            'cadence' => 'daily',
            'weekdays' => [],
        ])->assertStatus(201);
        $proposed = Challenge::where('name', 'Yoga')->firstOrFail();

        // A (proposer) attempts to accept own proposal.
        $this->asTg(self::TG_A)->postJson(
            "/api/battles/{$battle->id}/challenges/{$proposed->id}/accept",
        )->assertStatus(403);

        $this->assertTrue($proposed->fresh()->pending, 'challenge must stay pending after blocked self-accept');
    }

    // 4. Reject deletes the challenge row.

    public function test_other_participant_reject_deletes_challenge(): void
    {
        ['battle' => $battle] = $this->makeBattle();

        $this->asTg(self::TG_A)->postJson("/api/battles/{$battle->id}/challenges", [
            'name' => 'Swimming',
            'cadence' => 'daily',
            'weekdays' => [],
        ])->assertStatus(201);
        $proposed = Challenge::where('name', 'Swimming')->firstOrFail();

        $this->asTg(self::TG_B)->postJson(
            "/api/battles/{$battle->id}/challenges/{$proposed->id}/reject",
        )->assertStatus(200);

        $this->assertDatabaseMissing('challenges', ['id' => $proposed->id]);
    }

    // 5. description persists on create + propose.

    public function test_description_persists_on_create(): void
    {
        ['challenge' => $challenge] = $this->makeBattle([
            'description' => 'created with description',
        ]);

        $this->assertSame('created with description', $challenge->description);
    }

    public function test_description_persists_on_propose(): void
    {
        ['battle' => $battle] = $this->makeBattle();

        $this->asTg(self::TG_A)->postJson("/api/battles/{$battle->id}/challenges", [
            'name' => 'Meditation',
            'description' => 'proposed with description',
            'cadence' => 'daily',
            'weekdays' => [],
        ])->assertStatus(201);

        $proposed = Challenge::where('name', 'Meditation')->firstOrFail();
        $this->assertSame('proposed with description', $proposed->description);
    }

    // 6. Approving a completion credits the completing user's score server-side.
    //    (Confirms the reported "score doesn't count after accept" is a frontend-only issue.)

    public function test_approving_completion_credits_completing_user_score(): void
    {
        // A creates a battle with one DAILY challenge, starting today.
        ['battle' => $battle, 'challenge' => $challenge, 'userB' => $userB] = $this->makeBattle([
            'cadence' => 'daily',
            'weekdays' => [],
        ]);
        $userA = User::where('telegram_id', self::TG_A)->firstOrFail();

        $this->assertSame(
            Clock::todayLocal()->toDateString(),
            $challenge->start_date->toDateString(),
            'challenge should start today',
        );

        // B submits a completion today (Pending). Created via Eloquent to avoid multipart/PhotoService.
        $completion = Completion::create([
            'challenge_id' => $challenge->id,
            'user_id' => $userB->id,
            'day' => Clock::todayLocal()->toDateString(),
            'status' => CompletionStatus::Pending,
            'file_id' => 'dummy-file-id',
            'submitted_at' => now(),
        ]);

        // A approves it.
        $verify = $this->asTg(self::TG_A)->postJson(
            "/api/completions/{$completion->id}/verify",
            ['approve' => true],
        );
        $verify->assertStatus(200);
        $this->assertSame(CompletionStatus::Approved, $completion->fresh()->status);

        // GET battle detail and inspect per-player breakdown + score.
        $detail = $this->asTg(self::TG_A)->getJson("/api/battles/{$battle->id}");
        $detail->assertStatus(200);

        $playerB = collect($detail->json('players'))
            ->firstWhere('user.id', $userB->id);
        $playerA = collect($detail->json('players'))
            ->firstWhere('user.id', $userA->id);

        $this->assertNotNull($playerB, 'user B must be a player in the battle detail');
        $this->assertNotNull($playerA, 'user A must be a player in the battle detail');

        // B (completed + approved): breakdown for this challenge == 1, score >= 1.
        $this->assertSame(
            1,
            $playerB['breakdown'][(string) $challenge->id] ?? null,
            'approved completion must count as 1 in B breakdown',
        );
        $this->assertGreaterThanOrEqual(1, $playerB['score'], 'B score must be credited >= 1');

        // A (did not complete): breakdown for this challenge == 0.
        $this->assertSame(
            0,
            $playerA['breakdown'][(string) $challenge->id] ?? null,
            'A did not complete, breakdown must be 0',
        );
    }
}
