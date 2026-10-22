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
use App\Models\StakeFulfillment;
use App\Models\User;
use App\Services\BattleClosingService;
use App\Services\Telegram\NotificationService;
use App\Services\Telegram\TelegramClient;
use App\Support\Clock;
use App\Support\Coin;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Garov — yutuq va jazo (SPEC §17).
 *
 * Oqim: yaratuvchi o'z yutug'i + jazo variantini aytadi → do'st o'zinikini
 * aytib qabul qiladi → ikkalasi jazo bo'yicha bir variantni tanlaydi (yoki
 * tanga) → duel boshlanadi. Yutuq tanlanmaydi: g'olibning o'zi aytgani bajariladi.
 * Yakunda rejaning 50% yoki kamini bajargan jazoga tortiladi.
 */
class BattleStakesTest extends TestCase
{
    use RefreshDatabase;

    private const TG_A = 5001; // yaratuvchi

    private const TG_B = 5002; // do'st

    /** @var list<array{to: int, text: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();

        $sent = &$this->sent;
        $this->instance(NotificationService::class, new class($sent) extends NotificationService
        {
            /** @param  list<array{to: int, text: string}>  $sent */
            public function __construct(private array &$sent)
            {
                parent::__construct(new TelegramClient);
            }

            public function notify(iterable $telegramIds, string $text): void
            {
                foreach ($telegramIds as $id) {
                    $this->sent[] = ['to' => (int) $id, 'text' => $text];
                }
            }

            public function notifyWithApp(int $telegramId, string $text, string $buttonText, string $path = '/'): void
            {
                $this->sent[] = ['to' => $telegramId, 'text' => "{$text}\n[{$buttonText} → {$path}]"];
            }
        });
    }

    /** @return list<string> shu foydalanuvchiga ketgan xabarlar */
    private function sentTo(int $tg): array
    {
        return array_values(array_map(
            fn (array $m) => $m['text'],
            array_filter($this->sent, fn (array $m) => $m['to'] === $tg),
        ));
    }

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

    /** Ikkalasi ham yaratuvchining jazo variantini tanlaydi — duel boshlanadi. */
    private function agreeOnCreatorStakes(Battle $battle): void
    {
        $stake = $this->stake($battle, 'penalty', self::TG_A);
        $this->vote($battle, self::TG_A, $stake)->assertStatus(200);
        $this->vote($battle, self::TG_B, $stake)->assertStatus(200);
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

    public function test_agreeing_on_the_penalty_alone_starts_the_battle(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        $penaltyA = $this->stake($battle, 'penalty', self::TG_A);
        $this->vote($battle, self::TG_B, $penaltyA)->assertJsonPath('status', 'negotiating');
        $this->vote($battle, self::TG_A, $penaltyA)
            ->assertJsonPath('status', 'active')
            ->assertJsonPath('stakes.penalty.chosen.text', '100 push-up')
            // Yutuq tanlanmaydi — ikkala o'yinchining o'z yutug'i turadi
            ->assertJsonPath('stakes.prize.chosen', null)
            ->assertJsonPath('stakes.prize.options.0.text', 'Kofe')
            ->assertJsonPath('stakes.prize.options.0.is_mine', true)
            ->assertJsonPath('stakes.prize.options.1.text', 'Kino');

        $this->assertSame(BattleStatus::Active, $battle->fresh()->status);
    }

    public function test_prize_cannot_be_voted_or_flipped(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        $this->vote($battle, self::TG_A, $this->stake($battle, 'prize', self::TG_B))->assertStatus(409);
        $this->asTg(self::TG_A)->postJson("/api/battles/{$battle->id}/stakes/prize/coin")
            ->assertStatus(409);

        $this->assertSame(BattleStatus::Negotiating, $battle->fresh()->status);
    }

    public function test_start_message_tells_each_player_both_prizes(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);
        $this->sent = [];

        $this->agreeOnCreatorStakes($battle);

        // Kelishuv + boshlanish — alohida "Kelishildi" xabari takrorlanmaydi
        $all = implode("\n", array_column($this->sent, 'text'));
        $this->assertStringNotContainsString('Kelishildi', $all);

        $toA = array_values(array_filter($this->sentTo(self::TG_A), fn ($t) => str_contains($t, 'boshlandi')));
        $toB = array_values(array_filter($this->sentTo(self::TG_B), fn ($t) => str_contains($t, 'boshlandi')));
        $this->assertCount(1, $toA);
        $this->assertCount(1, $toB);

        // Har kim o'z nuqtai nazaridan: o'zi yutsa nima oladi, raqib yutsa nima beradi
        $this->assertStringContainsString('Sen yutsang, Dev senga: <b>Kofe</b>', $toA[0]);
        $this->assertStringContainsString('Dev yutsa, sen unga: <b>Kino</b>', $toA[0]);
        $this->assertStringContainsString('Sen yutsang, Dev senga: <b>Kino</b>', $toB[0]);
        $this->assertStringContainsString('<b>100 push-up</b>', $toB[0]);
    }

    public function test_user_text_is_escaped_in_bot_messages(): void
    {
        $battle = $this->createBattle(['stakes' => ['prize' => 'Pitsa <3', 'penalty' => '100 push-up']]);
        $this->acceptAsB($battle);
        $this->agreeOnCreatorStakes($battle);

        $this->assertStringContainsString('Pitsa &lt;3', implode("\n", $this->sentTo(self::TG_A)));
    }

    public function test_split_votes_wait_for_a_coin_flip(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        $penaltyA = $this->stake($battle, 'penalty', self::TG_A);
        $penaltyB = $this->stake($battle, 'penalty', self::TG_B);

        // Faqat bitta ovoz — tanga hali tashlanmaydi
        $this->vote($battle, self::TG_A, $penaltyA);
        $this->asTg(self::TG_A)->postJson("/api/battles/{$battle->id}/stakes/penalty/coin")
            ->assertStatus(409);

        // Ovozlar ikkiga bo'lindi — hal bo'lmagan
        $this->vote($battle, self::TG_B, $penaltyB)
            ->assertJsonPath('stakes.penalty.chosen', null)
            ->assertJsonPath('stakes.penalty.my_vote', $penaltyB->id)
            ->assertJsonPath('stakes.penalty.rival_vote', $penaltyA->id);

        // Tanga — natijani qotiramiz
        $this->instance(Coin::class, new class extends Coin
        {
            public function flip(array $sides): mixed
            {
                return $sides[1];
            }
        });

        $this->asTg(self::TG_B)->postJson("/api/battles/{$battle->id}/stakes/penalty/coin")
            ->assertStatus(200)
            ->assertJsonPath('stakes.penalty.chosen.via', 'coin')
            ->assertJsonPath('status', 'active', 'jazo hal bo\'ldi — duel boshlandi');

        $this->assertSame(1, BattleStake::where('battle_id', $battle->id)
            ->where('kind', 'penalty')
            ->where('chosen_via', StakeDecision::Coin->value)
            ->count());

        // Hal bo'lgan garovga endi ovoz berilmaydi
        $this->vote($battle, self::TG_A, $penaltyA)->assertStatus(409);
    }

    public function test_editing_the_penalty_resets_only_penalty_votes(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        $penaltyA = $this->stake($battle, 'penalty', self::TG_A);
        $this->vote($battle, self::TG_B, $penaltyA)->assertJsonPath('stakes.penalty.my_vote', $penaltyA->id);

        $this->asTg(self::TG_A)
            ->patchJson("/api/battles/{$battle->id}/stakes/penalty", ['text' => '50 ta o\'tirib-turish'])
            ->assertStatus(200)
            ->assertJsonPath('stakes.penalty.rival_vote', null);

        $this->assertSame('50 ta o\'tirib-turish', $penaltyA->fresh()->text);
    }

    public function test_own_prize_can_be_edited_before_start_and_rival_is_told(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        // Jazo bo'yicha ovoz — yutuq tahriri unga tegmasin
        $penaltyA = $this->stake($battle, 'penalty', self::TG_A);
        $this->vote($battle, self::TG_B, $penaltyA);
        $this->sent = [];

        $this->asTg(self::TG_B)
            ->patchJson("/api/battles/{$battle->id}/stakes/prize", ['text' => 'Konsert bileti'])
            ->assertStatus(200)
            ->assertJsonPath('status', 'negotiating')
            ->assertJsonPath('stakes.prize.options.0.text', 'Konsert bileti')
            ->assertJsonPath('stakes.penalty.my_vote', $penaltyA->id);

        $this->assertSame([], $this->sentTo(self::TG_B), 'o\'ziga xabar ketmaydi');
        $this->assertStringContainsString('sen unga: <b>Konsert bileti</b>', $this->sentTo(self::TG_A)[0]);
    }

    public function test_stakes_are_locked_once_the_battle_starts(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);
        $this->agreeOnCreatorStakes($battle);

        foreach (['prize', 'penalty'] as $kind) {
            $this->asTg(self::TG_A)
                ->patchJson("/api/battles/{$battle->id}/stakes/{$kind}", ['text' => 'Boshqa'])
                ->assertStatus(409);
        }
    }

    public function test_outsider_cannot_vote(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);

        $this->vote($battle, 5999, $this->stake($battle, 'penalty', self::TG_A))
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
            // G'olib (B) o'zi aytgan yutuqni oladi
            ->assertJsonPath('result.prize', 'Kino')
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

    // --- Va'dalar: kim kimga qarzdor va isbot (SPEC §17) ----------------------

    /**
     * Garovli 10 kunlik duelni o'tmishda tugatadi: A va B shuncha kun bajargan.
     */
    private function finishedBattle(int $daysA, int $daysB): Battle
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);
        $this->agreeOnCreatorStakes($battle);

        $today = Clock::todayLocal();
        $start = $today->subDays(11);
        $battle->update([
            'start_date' => $start->toDateString(),
            'end_date' => $today->subDays(2)->toDateString(),
        ]);
        $challenge = $battle->challenges()->firstOrFail();
        $challenge->update(['start_date' => $start->toDateString()]);

        $this->approve($challenge->id, User::where('telegram_id', self::TG_A)->value('id'), $start, $daysA);
        $this->approve($challenge->id, User::where('telegram_id', self::TG_B)->value('id'), $start, $daysB);

        $this->sent = [];
        app(BattleClosingService::class)->runDailyClose();

        return $battle->refresh();
    }

    public function test_loser_owes_the_winners_own_prize_and_penalized_owes_the_penalty(): void
    {
        // A 5/10 (yutqazdi, jazo), B 6/10 (yutdi, xavfsiz)
        $battle = $this->finishedBattle(5, 6);

        $this->asTg(self::TG_A)->getJson("/api/battles/{$battle->id}")
            ->assertJsonCount(2, 'result.obligations')
            ->assertJsonPath('result.obligations.0.kind', 'prize')
            ->assertJsonPath('result.obligations.0.text', 'Kino')
            ->assertJsonPath('result.obligations.0.is_mine', true)
            ->assertJsonPath('result.obligations.0.fulfillment', null)
            ->assertJsonPath('result.obligations.1.kind', 'penalty')
            ->assertJsonPath('result.obligations.1.text', '100 push-up')
            ->assertJsonPath('result.obligations.1.is_mine', true);

        $this->asTg(self::TG_B)->getJson("/api/battles/{$battle->id}")
            ->assertJsonPath('result.obligations.0.for_me', true)
            ->assertJsonPath('result.obligations.0.is_mine', false);
    }

    public function test_draw_means_both_owe_each_others_prize(): void
    {
        // Ikkalasi hammasini bajardi — ball teng, durang
        $battle = $this->finishedBattle(10, 10);
        $this->assertNull($battle->winner_id);

        $json = $this->asTg(self::TG_A)->getJson("/api/battles/{$battle->id}")
            ->assertJsonPath('result.is_draw', true)
            ->assertJsonPath('result.prize', null)
            ->json('result.obligations');

        $prizes = collect($json)->where('kind', 'prize');
        $this->assertCount(2, $prizes, 'ikkalasi ham bir-biriga');
        $this->assertSame('Kino', $prizes->firstWhere('is_mine', true)['text'], 'A — B ning yutug\'ini');
        $this->assertSame('Kofe', $prizes->firstWhere('for_me', true)['text'], 'B — A ning yutug\'ini');
        $this->assertCount(0, collect($json)->where('kind', 'penalty'), 'hech kim jazoga tortilmadi');

        $toA = implode("\n", $this->sentTo(self::TG_A));
        $this->assertStringContainsString('DURANG', $toA);
        $this->assertStringContainsString('Dev senga: <b>Kofe</b>', $toA);
        $this->assertStringContainsString('Sen Dev uchun: <b>Kino</b>', $toA);
    }

    public function test_finish_message_tells_the_loser_exactly_what_to_do(): void
    {
        $battle = $this->finishedBattle(5, 6);

        $toA = implode("\n", $this->sentTo(self::TG_A));
        $this->assertStringContainsString('Endi nima bo\'ladi', $toA);
        $this->assertStringContainsString('Sen Dev uchun: <b>Kino</b>', $toA);
        $this->assertStringContainsString('Jazo — sen bajarasan: <b>100 push-up</b>', $toA);
        $this->assertStringContainsString("[📸 Isbot yuklash → /battle/{$battle->id}]", $toA);

        $toB = implode("\n", $this->sentTo(self::TG_B));
        $this->assertStringContainsString('SEN YUTDING', $toB);
        $this->assertStringContainsString('Dev senga: <b>Kino</b>', $toB);
        $this->assertStringContainsString('Jazodan qutulding', $toB);
    }

    public function test_debtor_uploads_photo_proof_and_creditor_is_told(): void
    {
        $battle = $this->finishedBattle(5, 6);
        $this->sent = [];

        $this->asTg(self::TG_A)
            ->post("/api/battles/{$battle->id}/promises/prize", [
                'file' => UploadedFile::fake()->image('kino.jpg'),
                'note' => 'Avatar 3, 7-qator',
            ])
            ->assertStatus(200)
            ->assertJsonPath('obligations.0.fulfillment.media_type', 'photo')
            ->assertJsonPath('obligations.0.fulfillment.note', 'Avatar 3, 7-qator')
            ->assertJsonPath('obligations.1.fulfillment', null, 'jazo hali bajarilmagan');

        $toB = implode("\n", $this->sentTo(self::TG_B));
        $this->assertStringContainsString('va\'dasini bajardi', $toB);
        $this->assertStringContainsString("/battle/{$battle->id}]", $toB);

        // B ham natijada isbotni ko'radi (tarix)
        $this->asTg(self::TG_B)->getJson("/api/battles/{$battle->id}")
            ->assertJsonPath('result.obligations.0.fulfillment.media_type', 'photo');
    }

    public function test_video_proof_and_reupload_replaces_it(): void
    {
        $battle = $this->finishedBattle(5, 6);

        $this->asTg(self::TG_A)
            ->post("/api/battles/{$battle->id}/promises/penalty", [
                'file' => UploadedFile::fake()->image('a.jpg'),
            ])->assertStatus(200);

        $this->asTg(self::TG_A)
            ->post("/api/battles/{$battle->id}/promises/penalty", [
                'file' => UploadedFile::fake()->create('pushup.mp4', 3000, 'video/mp4'),
            ])
            ->assertStatus(200)
            ->assertJsonPath('obligations.1.fulfillment.media_type', 'video');

        $this->assertSame(1, StakeFulfillment::where('battle_id', $battle->id)->count());
    }

    public function test_only_the_debtor_can_upload_and_only_after_the_end(): void
    {
        $battle = $this->finishedBattle(5, 6);
        $photo = fn () => ['file' => UploadedFile::fake()->image('x.jpg')];

        // G'olib yutuq bo'yicha qarzdor emas, jazodan ham qutulgan
        $this->asTg(self::TG_B)->post("/api/battles/{$battle->id}/promises/prize", $photo())->assertStatus(403);
        $this->asTg(self::TG_B)->post("/api/battles/{$battle->id}/promises/penalty", $photo())->assertStatus(403);
        $this->asTg(5999)->post("/api/battles/{$battle->id}/promises/prize", $photo())->assertStatus(403);

        // Hali tugamagan duel
        $active = $this->createBattle();
        $this->acceptAsB($active);
        $this->agreeOnCreatorStakes($active);
        $this->asTg(self::TG_A)->post("/api/battles/{$active->id}/promises/prize", $photo())->assertStatus(409);
    }

    public function test_upload_rejects_wrong_type_and_oversized_files(): void
    {
        $battle = $this->finishedBattle(5, 6);

        $this->asTg(self::TG_A)
            ->postJson("/api/battles/{$battle->id}/promises/prize", [
                'file' => UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf'),
            ])->assertStatus(422);

        $this->asTg(self::TG_A)
            ->postJson("/api/battles/{$battle->id}/promises/prize", [
                'file' => UploadedFile::fake()->create('big.mp4', 25000, 'video/mp4'),
            ])->assertStatus(422);
    }

    public function test_stuck_negotiation_starts_after_the_prize_migration(): void
    {
        // Eski qoida: jazo kelishilgan, yutuq ovozini kutib turgan duel
        $battle = $this->createBattle();
        $this->acceptAsB($battle);
        $penaltyA = $this->stake($battle, 'penalty', self::TG_A);
        $penaltyA->update(['chosen_via' => StakeDecision::Agreement->value]);
        $this->assertSame(BattleStatus::Negotiating, $battle->fresh()->status);

        (require database_path('migrations/2026_10_22_000001_prizes_are_per_player.php'))->up();

        $this->assertSame(BattleStatus::Active, $battle->fresh()->status);
    }

    public function test_legacy_battle_with_an_agreed_prize_keeps_it_for_both(): void
    {
        $battle = $this->createBattle();
        $this->acceptAsB($battle);
        // Eski qoida bilan A ning yutug'i ("Kofe") ikkalasi uchun tanlangan
        $this->stake($battle, 'prize', self::TG_A)->update(['chosen_via' => StakeDecision::Agreement->value]);
        $this->agreeOnCreatorStakes($battle);

        $today = Clock::todayLocal();
        $start = $today->subDays(11);
        $battle->update(['start_date' => $start->toDateString(), 'end_date' => $today->subDays(2)->toDateString()]);
        $challenge = $battle->challenges()->firstOrFail();
        $challenge->update(['start_date' => $start->toDateString()]);
        $this->approve($challenge->id, User::where('telegram_id', self::TG_B)->value('id'), $start, 6);

        app(BattleClosingService::class)->runDailyClose();

        // B yutdi, lekin kelishilgani "Kofe" — eski kelishuv buzilmaydi
        $this->asTg(self::TG_B)->getJson("/api/battles/{$battle->id}")
            ->assertJsonPath('result.prize', 'Kofe');
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
