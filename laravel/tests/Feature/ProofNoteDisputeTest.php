<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CompletionStatus;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Dispute;
use App\Models\User;
use App\Services\CompletionService;
use App\Support\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProofNoteDisputeTest extends TestCase
{
    use RefreshDatabase;

    private const TG_A = 2001; // creator / verifier

    private const TG_B = 2002; // participant (completion owner)

    private const TG_C = 2003; // outsider / non-owner

    private function asTg(int $telegramId): self
    {
        return $this->withHeader('X-Dev-Telegram-Id', (string) $telegramId);
    }

    /**
     * Create a battle (A creator) with one daily challenge starting today, plus accepted participant B.
     *
     * @return array{battle: Battle, challenge: Challenge, userA: User, userB: User}
     */
    private function makeBattle(): array
    {
        $this->asTg(self::TG_A)->postJson('/api/battles', [
            'title' => 'Note & Dispute Battle',
            'start_date' => Clock::todayLocal()->toDateString(),
            'end_date' => Clock::todayLocal()->addDays(6)->toDateString(),
            'challenges' => [[
                'name' => 'Pushups',
                'icon' => '💪',
                'cadence' => 'daily',
                'proof_type' => 'camera',
                'weekdays' => [],
            ]],
        ])->assertStatus(200);

        $battle = Battle::firstOrFail();
        $userA = User::where('telegram_id', self::TG_A)->firstOrFail();

        $userB = User::create(['telegram_id' => self::TG_B, 'first_name' => 'Bob']);
        BattleParticipant::create([
            'battle_id' => $battle->id,
            'user_id' => $userB->id,
            'accepted' => true,
        ]);

        return [
            'battle' => $battle,
            'challenge' => $battle->challenges()->firstOrFail(),
            'userA' => $userA,
            'userB' => $userB,
        ];
    }

    // FEATURE 1 — note on completion submit.

    public function test_submit_persists_note_and_queue_serializes_it(): void
    {
        ['challenge' => $challenge, 'userB' => $userB] = $this->makeBattle();

        // Test CompletionService::submit directly with a note (PhotoService returns a dev placeholder).
        $service = app(CompletionService::class);
        $completion = $service->submit(
            user: $userB,
            challengeId: $challenge->id,
            contents: 'fake-image-bytes',
            filename: 'proof.jpg',
            note: 'felt strong today',
        );

        $this->assertSame('felt strong today', $completion->fresh()->note, 'note must persist on submit');

        // Rival (A) verify queue should serialize the completion WITH the note field.
        $queue = $this->asTg(self::TG_A)->getJson('/api/verify-queue');
        $queue->assertStatus(200);

        $entry = collect($queue->json())
            ->firstWhere('completion.id', $completion->id);

        $this->assertNotNull($entry, 'completion must appear in rival verify queue');
        $this->assertArrayHasKey('note', $entry['completion'], 'serialized completion must include note field');
        $this->assertSame('felt strong today', $entry['completion']['note']);
    }

    // FEATURE 2 — dispute backend contract.

    public function test_owner_can_dispute_rejected_completion_and_it_returns_to_queue(): void
    {
        ['battle' => $battle, 'challenge' => $challenge, 'userB' => $userB] = $this->makeBattle();

        // B has a pending completion...
        $completion = Completion::create([
            'challenge_id' => $challenge->id,
            'user_id' => $userB->id,
            'day' => Clock::todayLocal()->toDateString(),
            'status' => CompletionStatus::Pending,
            'file_id' => 'dev-file',
            'submitted_at' => now(),
        ]);

        // ...which A rejects.
        $this->asTg(self::TG_A)->postJson(
            "/api/completions/{$completion->id}/verify",
            ['approve' => false],
        )->assertStatus(200);
        $this->assertSame(CompletionStatus::Rejected, $completion->fresh()->status);

        // B disputes.
        $this->asTg(self::TG_B)->postJson(
            "/api/completions/{$completion->id}/dispute",
        )->assertStatus(200);

        $fresh = $completion->fresh();
        $this->assertSame(CompletionStatus::Pending, $fresh->status, 'dispute flips status back to Pending');
        $this->assertNull($fresh->resolved_at, 'dispute nulls resolved_at');
        $this->assertDatabaseHas('disputes', [
            'completion_id' => $completion->id,
            'opened_by' => $userB->id,
        ]);
        $this->assertSame(1, Dispute::where('completion_id', $completion->id)->count());

        // Reappears in A's verify queue.
        $queue = $this->asTg(self::TG_A)->getJson('/api/verify-queue');
        $queue->assertStatus(200);
        $this->assertNotNull(
            collect($queue->json())->firstWhere('completion.id', $completion->id),
            'disputed completion must reappear in rival verify queue',
        );
    }

    public function test_only_owner_can_dispute(): void
    {
        ['battle' => $battle, 'challenge' => $challenge, 'userB' => $userB] = $this->makeBattle();

        $completion = Completion::create([
            'challenge_id' => $challenge->id,
            'user_id' => $userB->id,
            'day' => Clock::todayLocal()->toDateString(),
            'status' => CompletionStatus::Rejected,
            'file_id' => 'dev-file',
            'submitted_at' => now(),
            'resolved_at' => now(),
        ]);

        // C is not the owner of the completion -> 403.
        $this->asTg(self::TG_C)->postJson(
            "/api/completions/{$completion->id}/dispute",
        )->assertStatus(403);

        $this->assertSame(CompletionStatus::Rejected, $completion->fresh()->status);
        $this->assertDatabaseCount('disputes', 0);
    }

    public function test_cannot_dispute_pending_completion(): void
    {
        ['challenge' => $challenge, 'userB' => $userB] = $this->makeBattle();

        $completion = Completion::create([
            'challenge_id' => $challenge->id,
            'user_id' => $userB->id,
            'day' => Clock::todayLocal()->toDateString(),
            'status' => CompletionStatus::Pending,
            'file_id' => 'dev-file',
            'submitted_at' => now(),
        ]);

        $this->asTg(self::TG_B)->postJson(
            "/api/completions/{$completion->id}/dispute",
        )->assertStatus(409);

        $this->assertDatabaseCount('disputes', 0);
    }

    public function test_cannot_dispute_approved_completion(): void
    {
        ['challenge' => $challenge, 'userB' => $userB] = $this->makeBattle();

        $completion = Completion::create([
            'challenge_id' => $challenge->id,
            'user_id' => $userB->id,
            'day' => Clock::todayLocal()->toDateString(),
            'status' => CompletionStatus::Approved,
            'file_id' => 'dev-file',
            'submitted_at' => now(),
            'resolved_at' => now(),
        ]);

        $this->asTg(self::TG_B)->postJson(
            "/api/completions/{$completion->id}/dispute",
        )->assertStatus(409);

        $this->assertDatabaseCount('disputes', 0);
    }

    // FEATURE 3 — today payload includes completion_id.

    public function test_today_includes_completion_id_when_present_and_null_when_absent(): void
    {
        ['battle' => $battle, 'challenge' => $challenge, 'userB' => $userB] = $this->makeBattle();

        // A has NO completion today -> completion_id null.
        $todayA = $this->asTg(self::TG_A)->getJson("/api/battles/{$battle->id}/today");
        $todayA->assertStatus(200);
        $taskA = collect($todayA->json())->firstWhere('challenge.id', $challenge->id);
        $this->assertNotNull($taskA, 'daily challenge must be a today-task');
        $this->assertNull($taskA['completion_id'], 'completion_id must be null when no completion exists');

        // B has a completion today -> completion_id matches.
        $completion = Completion::create([
            'challenge_id' => $challenge->id,
            'user_id' => $userB->id,
            'day' => Clock::todayLocal()->toDateString(),
            'status' => CompletionStatus::Pending,
            'file_id' => 'dev-file',
            'submitted_at' => now(),
        ]);

        $todayB = $this->asTg(self::TG_B)->getJson("/api/battles/{$battle->id}/today");
        $todayB->assertStatus(200);
        $taskB = collect($todayB->json())->firstWhere('challenge.id', $challenge->id);
        $this->assertNotNull($taskB);
        $this->assertSame($completion->id, $taskB['completion_id'], 'completion_id must match existing completion');
    }
}
