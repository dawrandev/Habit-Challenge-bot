<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BattleStatus;
use App\Enums\CompletionStatus;
use App\Enums\StakeDecision;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\BattleStake;
use App\Models\Completion;
use App\Models\User;
use App\Services\BattleClosingService;
use App\Support\Clock;
use App\Support\Coin;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Garov — yutuq va jazo kelishuvi (SPEC §17).
 *
 * Oqim: yaratuvchi variant aytadi → do'st o'zinikini aytib qabul qiladi →
 * ikkalasi har garov bo'yicha bir variantni tanlaydi (yoki tanga) → duel boshlanadi.
 * Yakunda rejaning 50% yoki kamini bajargan jazoga tortiladi.
 */
class BattleStakesTest extends TestCase
{
    use RefreshDatabase;

    private const TG_A = 5001; // yaratuvchi

    private const TG_B = 5002; // do'st

    private function asTg(int $telegramId): self
    {
        return $this->withHeader('X-Dev-Telegram-Id', (string) $telegramId);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createBattle(array $overrides = []): Battle
    {
        $today = Clock::todayLocal();

        $this->asTg(self::TG_A)->postJson('/api/battles', array_merge([
            'title' => 'Garovli duel',
            'start_date' => $today->toDateString(),
            'end_date' => $today->addDays(9)->toDateString(),
            'challenges' => [
                ['name' => 'Sport', 'icon' => '🏃', 'cadence' => 'daily', 'weekdays' => []],
            ],
            'stakes' => ['prize' => 'Kofe', 'penalty' => '100 push-up'],
        ], $overrides))->assertStatus(200);

        return Battle::latest('id')->firstOrFail();
    }

    private function acceptAsB(Battle $battle): void
    {
        $this->asTg(self::TG_B)
            ->postJson("/api/battles/{$battle->invite_token}/accept", [
                'stakes' => ['prize' => 'Kino', 'penalty' => '1 hafta shirinliksiz'],
            ])
            ->assertStatus(200);
    }

    private function stake(Battle $battle, string $kind, int $tg): BattleStake
    {
        $user = User::where('telegram_id', $tg)->firstOrFail();

        return BattleStake::where('battle_id', $battle->id)
            ->where('kind', $kind)
            ->where('proposed_by', $user->id)
            ->firstOrFail();
    }

    private function vote(Battle $battle, int $tg, BattleStake $stake): TestResponse
    {
        return $this->asTg($tg)->postJson("/api/battles/{$battle->id}/stakes/{$stake->id}/vote");
    }

    /** Ikkalasi ham ikkala garovda yaratuvchining variantini tanlaydi. */
    private function agreeOnCreatorStakes(Battle $battle): void
    {
        foreach (['prize', 'penalty'] as $kind) {
            $stake = $this->stake($battle, $kind, self::TG_A);
            $this->vote($battle, self::TG_A, $stake)->assertStatus(200);
            $this->vote($battle, self::TG_B, $stake)->assertStatus(200);
        }
    }

    // --- Yaratish va taklif ------------------------------------------------

    public function test_invite_preview_shows_the_creators_proposals(): void
    {
        $battle = $this->createBattle();

        $this->assertSame(BattleStatus::Pending, $battle->status);

        $this->asTg(self::TG_B)
            ->getJson("/api/battles/invite/{$battle->invite_token}")
            ->assertStatus(200)
            ->assertJsonPath('stakes.prize', 'Kofe')
            ->assertJsonPath('stakes.penalty', '100 push-up')
            ->assertJsonPath('stakes.threshold', 50);
    }

    public function test_create_rejects_half_filled_stakes(): void
    {
        $today = Clock::todayLocal();

        $this->asTg(self::TG_A)->postJson('/api/battles', [
            'title' => 'X',
            'start_date' => $today->toDateString(),
            'end_date' => $today->addDays(6)->toDateString(),
            'challenges' => [['name' => 'Sport', 'cadence' => 'daily', 'weekdays' => []]],
            'stakes' => ['prize' => 'Kofe'],
        ])->assertStatus(422)->assertJsonValidationErrors('stakes.penalty');
    }

    public function test_accept_requires_own_proposals(): void
    {
        $battle = $this->createBattle();

        $this->asTg(self::TG_B)
            ->postJson("/api/battles/{$battle->invite_token}/accept")
            ->assertStatus(422);

        $this->assertSame(1, $battle->participants()->count(), 'variantsiz qo\'shilmasin');
        $this->assertSame(BattleStatus::Pending, $battle->fresh()->status);
    }

    public function test_accept_moves_to_negotiation_and_blocks_proofs(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        $battle->refresh();
        $this->assertSame(BattleStatus::Negotiating, $battle->status);
        $this->assertSame(4, $battle->stakes()->count(), '2 o\'yinchi × 2 garov');

        // Duel hali boshlanmagan — bugun kutilayotgan ish ham, isbot ham yo'q
        $this->asTg(self::TG_B)->getJson("/api/battles/{$battle->id}/today")
            ->assertStatus(200)
            ->assertExactJson([]);
        $this->assertFalse($battle->canSubmit(User::where('telegram_id', self::TG_B)->value('id')));

        // Chat ishlaydi — kelishish uchun kerak
        $this->assertTrue($battle->isOpen());
    }

    public function test_legacy_battle_without_stakes_starts_immediately(): void
    {
        $battle = $this->createBattle(['stakes' => null]);

        $this->asTg(self::TG_B)
            ->postJson("/api/battles/{$battle->invite_token}/accept")
            ->assertStatus(200);

        $this->assertSame(BattleStatus::Active, $battle->fresh()->status);
        $this->asTg(self::TG_A)->getJson("/api/battles/{$battle->id}")
            ->assertJsonPath('stakes', null);
    }

    public function test_third_person_cannot_join_a_negotiating_battle(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        $this->asTg(5003)
            ->postJson("/api/battles/{$battle->invite_token}/accept", [
                'stakes' => ['prize' => 'a1', 'penalty' => 'b1'],
            ])
            ->assertStatus(409);

        $this->assertSame(2, $battle->participants()->count());
    }

    // --- Kelishuv ----------------------------------------------------------

    public function test_matching_votes_on_both_stakes_start_the_battle(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        $prizeB = $this->stake($battle, 'prize', self::TG_B);
        $this->vote($battle, self::TG_A, $prizeB)->assertJsonPath('status', 'negotiating');
        $this->vote($battle, self::TG_B, $prizeB)
            ->assertJsonPath('stakes.prize.chosen.text', 'Kino')
            ->assertJsonPath('stakes.prize.chosen.via', 'agreement')
            ->assertJsonPath('status', 'negotiating', 'jazo hali hal bo\'lmagan');

        $penaltyA = $this->stake($battle, 'penalty', self::TG_A);
        $this->vote($battle, self::TG_B, $penaltyA);
        $this->vote($battle, self::TG_A, $penaltyA)->assertJsonPath('status', 'active');

        $this->assertSame(BattleStatus::Active, $battle->fresh()->status);
    }

    public function test_split_votes_wait_for_a_coin_flip(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        $prizeA = $this->stake($battle, 'prize', self::TG_A);
        $prizeB = $this->stake($battle, 'prize', self::TG_B);

        // Faqat bitta ovoz — tanga hali tashlanmaydi
        $this->vote($battle, self::TG_A, $prizeA);
        $this->asTg(self::TG_A)->postJson("/api/battles/{$battle->id}/stakes/prize/coin")
            ->assertStatus(409);

        // Ovozlar ikkiga bo'lindi — hal bo'lmagan
        $this->vote($battle, self::TG_B, $prizeB)
            ->assertJsonPath('stakes.prize.chosen', null)
            ->assertJsonPath('stakes.prize.my_vote', $prizeB->id)
            ->assertJsonPath('stakes.prize.rival_vote', $prizeA->id);

        // Tanga — natijani qotiramiz
        $this->instance(Coin::class, new class extends Coin
        {
            public function flip(array $sides): mixed
            {
                return $sides[1];
            }
        });

        $this->asTg(self::TG_B)->postJson("/api/battles/{$battle->id}/stakes/prize/coin")
            ->assertStatus(200)
            ->assertJsonPath('stakes.prize.chosen.via', 'coin');

        $this->assertSame(1, BattleStake::where('battle_id', $battle->id)
            ->where('kind', 'prize')
            ->where('chosen_via', StakeDecision::Coin->value)
            ->count());

        // Hal bo'lgan garovga endi ovoz berilmaydi
        $this->vote($battle, self::TG_A, $prizeA)->assertStatus(409);
    }

    public function test_editing_a_proposal_resets_votes_on_that_stake_only(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        $prizeA = $this->stake($battle, 'prize', self::TG_A);
        $penaltyA = $this->stake($battle, 'penalty', self::TG_A);
        $this->vote($battle, self::TG_B, $prizeA);
        $this->vote($battle, self::TG_B, $penaltyA);

        $this->asTg(self::TG_A)
            ->patchJson("/api/battles/{$battle->id}/stakes/prize", ['text' => 'Tushlik'])
            ->assertStatus(200)
            ->assertJsonPath('stakes.prize.rival_vote', null)
            ->assertJsonPath('stakes.penalty.rival_vote', $penaltyA->id);

        $this->assertSame('Tushlik', $prizeA->fresh()->text);
    }

    public function test_decided_stake_cannot_be_edited(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);
        $this->agreeOnCreatorStakes($battle);

        $this->asTg(self::TG_A)
            ->patchJson("/api/battles/{$battle->id}/stakes/prize", ['text' => 'Boshqa'])
            ->assertStatus(409);
    }

    public function test_outsider_cannot_vote(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        $this->vote($battle, 5999, $this->stake($battle, 'prize', self::TG_A))
            ->assertStatus(403);
    }

    public function test_late_agreement_shifts_dates_and_keeps_the_period_length(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        // Kelishuv 3 kun cho'zildi — rejadagi boshlanish o'tib ketdi
        $today = Clock::todayLocal();
        $battle->update([
            'start_date' => $today->subDays(3)->toDateString(),
            'end_date' => $today->addDays(6)->toDateString(),
        ]);
        $battle->challenges()->update(['start_date' => $today->subDays(3)->toDateString()]);

        $this->agreeOnCreatorStakes($battle);

        $battle->refresh();
        $this->assertSame($today->toDateString(), $battle->start_date->toDateString());
        $this->assertSame($today->addDays(9)->toDateString(), $battle->end_date->toDateString(), '10 kun saqlanadi');
        $this->assertSame($today->toDateString(), $battle->challenges()->first()->start_date->toDateString());

        // O'tib ketgan kunlar jarima bo'lmaydi
        $this->asTg(self::TG_A)->getJson("/api/battles/{$battle->id}")
            ->assertJsonPath('players.0.score', 0);
    }

    // --- Jazo hisobi va yakun ------------------------------------------------

    public function test_live_progress_counts_the_whole_plan(): void
    {
        $battle = $this->createBattle([
            'challenges' => [
                ['name' => 'Sport', 'cadence' => 'daily', 'weekdays' => []],
                ['name' => 'Kitob', 'cadence' => 'daily', 'weekdays' => []],
            ],
        ]);
        $this->acceptAsB($battle);
        $this->agreeOnCreatorStakes($battle);

        // 2 odat × 10 kun = 20, xavfsizlik — 11 dan
        $this->asTg(self::TG_A)->getJson("/api/battles/{$battle->id}")
            ->assertJsonPath('players.0.habits.expected', 20)
            ->assertJsonPath('players.0.habits.done', 0)
            ->assertJsonPath('players.0.habits.safe_at', 11)
            ->assertJsonPath('players.0.habits.penalized', null);
    }

    /**
     * 1 odat × 10 kun: A 5 ta (50% — jazo), B 6 ta (xavfsiz, va yutadi).
     */
    public function test_closing_seals_penalty_and_reports_prize(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);
        $this->agreeOnCreatorStakes($battle);

        // Davrni o'tmishga suramiz: oldingi kuni tugagan 10 kunlik duel
        // (kechagi hisobot oynasi ham o'tgan — SPEC §18)
        $today = Clock::todayLocal();
        $start = $today->subDays(11);
        $battle->update([
            'start_date' => $start->toDateString(),
            'end_date' => $today->subDays(2)->toDateString(),
        ]);
        $challenge = $battle->challenges()->firstOrFail();
        $challenge->update(['start_date' => $start->toDateString()]);

        $a = User::where('telegram_id', self::TG_A)->firstOrFail();
        $b = User::where('telegram_id', self::TG_B)->firstOrFail();
        $this->approve($challenge->id, $a->id, $start, 5);
        $this->approve($challenge->id, $b->id, $start, 6);

        app(BattleClosingService::class)->runDailyClose();

        $battle->refresh();
        $this->assertSame(BattleStatus::Finished, $battle->status);
        $this->assertSame($b->id, $battle->winner_id);

        $pa = BattleParticipant::where('battle_id', $battle->id)->where('user_id', $a->id)->firstOrFail();
        $pb = BattleParticipant::where('battle_id', $battle->id)->where('user_id', $b->id)->firstOrFail();
        $this->assertSame([5, 10, true], [$pa->habits_done, $pa->habits_expected, $pa->penalized]);
        $this->assertSame([6, 10, false], [$pb->habits_done, $pb->habits_expected, $pb->penalized]);

        $this->asTg(self::TG_B)->getJson("/api/battles/{$battle->id}")
            ->assertJsonPath('result.you_won', true)
            ->assertJsonPath('result.prize', 'Kofe')
            ->assertJsonPath('result.penalty', '100 push-up');

        // Muhrlangan natija keyin challenge o'chirilsa ham o'zgarmaydi
        $challenge->delete();
        $json = $this->asTg(self::TG_A)->getJson("/api/battles/{$battle->id}")->json('players');
        $mine = collect($json)->firstWhere('is_me', true);
        $this->assertSame(['done' => 5, 'expected' => 10, 'safe_at' => 6, 'penalized' => true], $mine['habits']);
    }

    public function test_battle_without_stakes_never_marks_anyone_penalized(): void
    {
        $battle = $this->createBattle(['stakes' => null]);
        $this->asTg(self::TG_B)->postJson("/api/battles/{$battle->invite_token}/accept");

        $today = Clock::todayLocal();
        $battle->update([
            'start_date' => $today->subDays(4)->toDateString(),
            'end_date' => $today->subDays(2)->toDateString(),
        ]);
        $battle->challenges()->update(['start_date' => $today->subDays(4)->toDateString()]);

        app(BattleClosingService::class)->runDailyClose();

        $this->assertSame(
            [null, null],
            BattleParticipant::where('battle_id', $battle->id)->pluck('penalized')->all(),
        );
    }

    private function approve(int $challengeId, int $userId, CarbonImmutable $start, int $days): void
    {
        for ($d = 0; $d < $days; $d++) {
            Completion::create([
                'challenge_id' => $challengeId,
                'user_id' => $userId,
                'day' => $start->addDays($d)->toDateString(),
                'status' => CompletionStatus::Approved,
                'submitted_at' => now(),
                'resolved_at' => now(),
                'file_id' => 'test',
            ]);
        }
    }
}
