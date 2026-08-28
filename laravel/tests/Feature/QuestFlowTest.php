<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CompletionStatus;
use App\Enums\QuestStatus;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Quest;
use App\Models\User;
use App\Services\CompletionService;
use App\Support\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Missiya — asimmetrik rejim.
 *
 * Butun mohiyat shu: odat FAQAT eganiki. Guvoh uni bajarmaydi, faqat tekshiradi.
 * Shu asimmetriya buzilmasligi bu yerda mixlab qo'yilgan.
 */
class QuestFlowTest extends TestCase
{
    use RefreshDatabase;

    private const TG_OWNER = 3001;

    private const TG_WITNESS = 3002;

    private const TG_STRANGER = 3003;

    private function asTg(int $telegramId): self
    {
        return $this->withHeader('X-Dev-Telegram-Id', (string) $telegramId);
    }

    /**
     * @return array{quest: Quest, challenge: Challenge, owner: User}
     */
    private function makeQuest(int $periodDays = 30, int $goal = 80): array
    {
        $this->asTg(self::TG_OWNER)->postJson('/api/quests', [
            'title' => 'Ertalabki yugurish',
            'period_days' => $periodDays,
            'start_tomorrow' => false,
            'goal_percent' => $goal,
            'challenges' => [[
                'name' => 'Yugurish',
                'icon' => '🏃',
                'cadence' => 'daily',
                'proof_type' => 'camera',
                'weekdays' => [],
            ]],
        ])->assertStatus(200);

        $quest = Quest::firstOrFail();

        return [
            'quest' => $quest,
            'challenge' => $quest->challenges()->firstOrFail(),
            'owner' => User::where('telegram_id', self::TG_OWNER)->firstOrFail(),
        ];
    }

    private function joinWitness(Quest $quest, int $telegramId = self::TG_WITNESS): User
    {
        $this->asTg($telegramId)
            ->postJson("/api/quests/{$quest->invite_token}/accept")
            ->assertStatus(200);

        return User::where('telegram_id', $telegramId)->firstOrFail();
    }

    private function submitProof(User $user, Challenge $challenge): Completion
    {
        return app(CompletionService::class)->submit($user, $challenge->id, 'fake-bytes');
    }

    // --- Yaratish -----------------------------------------------------------

    public function test_created_quest_has_owner_no_witness_and_exact_period(): void
    {
        ['quest' => $quest] = $this->makeQuest(periodDays: 30);

        $owner = User::where('telegram_id', self::TG_OWNER)->firstOrFail();
        $this->assertSame($owner->id, $quest->owner_id);
        $this->assertNull($quest->witness_id, 'guvoh keyin qo`shiladi');
        $this->assertSame(QuestStatus::Active, $quest->status, 'missiya guvohsiz ham darhol boshlanadi');

        // Inklyuziv davr: 30 kunlik missiya ayni 30 kun
        $this->assertSame(
            29,
            (int) $quest->start_date->diffInDays($quest->end_date),
        );
    }

    public function test_quest_challenges_are_not_pending_unlike_battle_proposals(): void
    {
        // Duelda o'rtada qo'shilgan challenge kelishuv kutadi; missiyada odat
        // eganiki — guvohning roziligi shart emas.
        ['quest' => $quest] = $this->makeQuest();

        $this->assertFalse($quest->challenges()->firstOrFail()->pending);
    }

    // --- Guvoh taklifi ------------------------------------------------------

    public function test_owner_cannot_witness_their_own_quest(): void
    {
        ['quest' => $quest] = $this->makeQuest();

        $this->asTg(self::TG_OWNER)
            ->postJson("/api/quests/{$quest->invite_token}/accept")
            ->assertStatus(409);

        $this->assertNull($quest->fresh()->witness_id);
    }

    public function test_witness_slot_holds_exactly_one_person(): void
    {
        ['quest' => $quest] = $this->makeQuest();
        $this->joinWitness($quest);

        // Uchinchi odam bo'sh bo'lmagan joyni egallay olmaydi
        $this->asTg(self::TG_STRANGER)
            ->postJson("/api/quests/{$quest->invite_token}/accept")
            ->assertStatus(409);

        $witness = User::where('telegram_id', self::TG_WITNESS)->firstOrFail();
        $this->assertSame($witness->id, $quest->fresh()->witness_id);
    }

    public function test_accepting_twice_is_idempotent(): void
    {
        ['quest' => $quest] = $this->makeQuest();
        $this->joinWitness($quest);

        $this->asTg(self::TG_WITNESS)
            ->postJson("/api/quests/{$quest->invite_token}/accept")
            ->assertStatus(200);
    }

    // --- Asimmetriya: kim bajaradi ------------------------------------------

    public function test_only_the_owner_can_submit_proof(): void
    {
        ['quest' => $quest, 'challenge' => $challenge, 'owner' => $owner] = $this->makeQuest();
        $witness = $this->joinWitness($quest);

        // Ega — mumkin
        $completion = $this->submitProof($owner, $challenge);
        $this->assertSame(CompletionStatus::Pending, $completion->status);

        // Guvoh — mumkin emas: odat uniki emas
        $this->expectExceptionMessageMatches('/isbot yubora olmaysan/');
        $this->submitProof($witness, $challenge);
    }

    public function test_stranger_cannot_submit_proof(): void
    {
        ['quest' => $quest, 'challenge' => $challenge] = $this->makeQuest();
        $this->joinWitness($quest);

        $stranger = User::create(['telegram_id' => self::TG_STRANGER, 'first_name' => 'Begona']);

        $this->expectExceptionMessageMatches('/isbot yubora olmaysan/');
        $this->submitProof($stranger, $challenge);
    }

    public function test_today_tasks_are_empty_for_the_witness(): void
    {
        ['quest' => $quest] = $this->makeQuest();
        $this->joinWitness($quest);

        $this->asTg(self::TG_OWNER)
            ->getJson("/api/quests/{$quest->id}/today")
            ->assertStatus(200)
            ->assertJsonCount(1);

        $this->asTg(self::TG_WITNESS)
            ->getJson("/api/quests/{$quest->id}/today")
            ->assertStatus(200)
            ->assertJsonCount(0, null);
    }

    // --- Asimmetriya: kim tekshiradi ----------------------------------------

    public function test_witness_verifies_and_owner_cannot_verify_own_proof(): void
    {
        ['quest' => $quest, 'challenge' => $challenge, 'owner' => $owner] = $this->makeQuest();
        $this->joinWitness($quest);

        $completion = $this->submitProof($owner, $challenge);

        // Ega o'zini tasdiqlay olmaydi — aks holda butun g'oya buziladi
        $this->asTg(self::TG_OWNER)
            ->postJson("/api/completions/{$completion->id}/verify", ['approve' => true])
            ->assertStatus(403);

        // Guvoh — mumkin
        $this->asTg(self::TG_WITNESS)
            ->postJson("/api/completions/{$completion->id}/verify", ['approve' => true])
            ->assertStatus(200);

        $this->assertSame(CompletionStatus::Approved, $completion->fresh()->status);
    }

    public function test_stranger_cannot_verify(): void
    {
        ['quest' => $quest, 'challenge' => $challenge, 'owner' => $owner] = $this->makeQuest();
        $this->joinWitness($quest);
        $completion = $this->submitProof($owner, $challenge);

        $this->asTg(self::TG_STRANGER)
            ->postJson("/api/completions/{$completion->id}/verify", ['approve' => true])
            ->assertStatus(403);
    }

    public function test_verify_queue_shows_quest_proofs_to_the_witness_only(): void
    {
        ['quest' => $quest, 'challenge' => $challenge, 'owner' => $owner] = $this->makeQuest();
        $this->joinWitness($quest);
        $this->submitProof($owner, $challenge);

        $this->asTg(self::TG_WITNESS)
            ->getJson('/api/verify-queue')
            ->assertStatus(200)
            ->assertJsonCount(1)
            ->assertJsonPath('0.context.key', 'quest')
            ->assertJsonPath('0.context.id', $quest->id);

        // Ega o'z hisobotini navbatda ko'rmaydi
        $this->asTg(self::TG_OWNER)
            ->getJson('/api/verify-queue')
            ->assertStatus(200)
            ->assertJsonCount(0, null);

        $this->asTg(self::TG_STRANGER)
            ->getJson('/api/verify-queue')
            ->assertStatus(200)
            ->assertJsonCount(0, null);
    }

    public function test_quest_without_witness_accepts_proof_but_queues_it_for_nobody(): void
    {
        ['challenge' => $challenge, 'owner' => $owner] = $this->makeQuest();

        // Guvoh yo'q — isbot baribir qabul qilinadi (24s dan keyin avto-tasdiq)
        $completion = $this->submitProof($owner, $challenge);
        $this->assertSame(CompletionStatus::Pending, $completion->status);

        $this->asTg(self::TG_STRANGER)
            ->getJson('/api/verify-queue')
            ->assertStatus(200)
            ->assertJsonCount(0, null);
    }

    // --- Kirish nazorati ----------------------------------------------------

    public function test_stranger_cannot_read_quest_detail(): void
    {
        ['quest' => $quest] = $this->makeQuest();
        $this->joinWitness($quest);

        $this->asTg(self::TG_STRANGER)
            ->getJson("/api/quests/{$quest->id}")
            ->assertStatus(403);

        $this->asTg(self::TG_WITNESS)
            ->getJson("/api/quests/{$quest->id}")
            ->assertStatus(200)
            ->assertJsonPath('role', 'witness');

        $this->asTg(self::TG_OWNER)
            ->getJson("/api/quests/{$quest->id}")
            ->assertStatus(200)
            ->assertJsonPath('role', 'owner');
    }

    public function test_only_owner_can_edit_or_delete_the_quest(): void
    {
        ['quest' => $quest, 'challenge' => $challenge] = $this->makeQuest();
        $this->joinWitness($quest);

        $this->asTg(self::TG_WITNESS)
            ->patchJson("/api/quests/{$quest->id}", ['title' => 'Guvoh nomi'])
            ->assertStatus(403);

        $this->asTg(self::TG_WITNESS)
            ->deleteJson("/api/quests/{$quest->id}/challenges/{$challenge->id}")
            ->assertStatus(403);

        $this->asTg(self::TG_OWNER)
            ->patchJson("/api/quests/{$quest->id}", ['title' => 'Yangi nom'])
            ->assertStatus(200);

        $this->assertSame('Yangi nom', $quest->fresh()->title);
    }

    public function test_quest_list_includes_both_roles(): void
    {
        ['quest' => $quest] = $this->makeQuest();
        $this->joinWitness($quest);

        $this->asTg(self::TG_OWNER)->getJson('/api/quests')
            ->assertStatus(200)->assertJsonCount(1)->assertJsonPath('0.role', 'owner');

        $this->asTg(self::TG_WITNESS)->getJson('/api/quests')
            ->assertStatus(200)->assertJsonCount(1)->assertJsonPath('0.role', 'witness');

        $this->asTg(self::TG_STRANGER)->getJson('/api/quests')
            ->assertStatus(200)->assertJsonCount(0, null);
    }

    // --- Statistika (chart ma'lumotlari) ------------------------------------

    public function test_detail_exposes_stats_payload_for_the_charts(): void
    {
        ['quest' => $quest, 'challenge' => $challenge, 'owner' => $owner] = $this->makeQuest(periodDays: 7);
        $this->joinWitness($quest);

        $completion = $this->submitProof($owner, $challenge);
        $this->asTg(self::TG_WITNESS)
            ->postJson("/api/completions/{$completion->id}/verify", ['approve' => true]);

        $response = $this->asTg(self::TG_OWNER)
            ->getJson("/api/quests/{$quest->id}")
            ->assertStatus(200);

        $response->assertJsonStructure([
            'stats' => [
                'done', 'missed', 'pending', 'resolved', 'planned',
                'rate', 'ceiling_rate', 'current_streak', 'longest_streak',
                'days_elapsed', 'days_total', 'goal_percent', 'goal_reachable',
                'days' => [['date', 'due', 'done', 'state']],
                'challenges' => [['challenge_id', 'done', 'missed', 'rate', 'cells']],
            ],
        ]);

        $stats = $response->json('stats');
        $this->assertSame(1, $stats['done']);
        $this->assertSame(1, $stats['current_streak']);
        // JSON'da 100.0 → 100 bo'lib uzatiladi (JS tarafda farqi yo'q)
        $this->assertEqualsWithDelta(100.0, $stats['rate'], 0.01);
        $this->assertCount(7, $stats['days']);
        $this->assertCount(7, $stats['challenges'][0]['cells'], 'heatmap qatori sana o`qiga mos');
    }

    public function test_finishing_a_due_quest_sets_the_outcome(): void
    {
        ['quest' => $quest, 'challenge' => $challenge, 'owner' => $owner] = $this->makeQuest(periodDays: 3, goal: 50);
        $this->joinWitness($quest);

        // Bugungi isbot tasdiqlansin
        $completion = $this->submitProof($owner, $challenge);
        $this->asTg(self::TG_WITNESS)
            ->postJson("/api/completions/{$completion->id}/verify", ['approve' => true]);

        // Davrni o'tmishga surib, yopish cron'ini yuritamiz
        $yesterday = Clock::todayLocal()->subDay()->toDateString();
        $quest->forceFill([
            'start_date' => Clock::todayLocal()->subDays(2)->toDateString(),
            'end_date' => $yesterday,
        ])->save();
        Completion::query()->update(['day' => $yesterday]);
        Challenge::query()->update(['start_date' => Clock::todayLocal()->subDays(2)->toDateString()]);

        $this->artisan('battle:close-day')->assertExitCode(0);

        $fresh = $quest->fresh();
        $this->assertSame(QuestStatus::Finished, $fresh->status);
        $this->assertNotNull($fresh->outcome, 'yakunda natija qo`yilishi shart');
    }
}
