<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\BattleStatus;
use App\Enums\Cadence;
use App\Enums\CompletionStatus;
use App\Enums\QuestStatus;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Quest;
use App\Models\User;
use App\Services\ReminderService;
use App\Services\Telegram\NotificationService;
use App\Services\Telegram\TelegramClient;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Eslatmalar — SPEC §18: odat vaqtida, kechqurun 23:00 va ertalab 10:00 (kechagilar).
 */
class ReminderTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<array{to: int, text: string, path: string}> */
    private array $sent = [];

    private User $me;

    private User $rival;

    protected function setUp(): void
    {
        parent::setUp();

        $sent = &$this->sent;
        $this->instance(NotificationService::class, new class($sent) extends NotificationService
        {
            /** @param  list<array{to: int, text: string, path: string}>  $sent */
            public function __construct(private array &$sent)
            {
                parent::__construct(new TelegramClient);
            }

            public function notifyWithApp(int $telegramId, string $text, string $buttonText, string $path = '/'): void
            {
                $this->sent[] = ['to' => $telegramId, 'text' => $text, 'path' => $path];
            }
        });

        config([
            'telegram.reminders.evening_at' => '23:00',
            'telegram.reminders.late_at' => '10:00',
            'telegram.reminders.window_minutes' => 120,
        ]);

        $this->me = User::create(['telegram_id' => 8001, 'first_name' => 'Men']);
        $this->rival = User::create(['telegram_id' => 8002, 'first_name' => 'Raqib']);
    }

    /** Toshkent vaqti bilan "hozir". */
    private function at(string $dateTime): CarbonImmutable
    {
        $now = CarbonImmutable::parse($dateTime, config('telegram.timezone'));
        $this->travelTo($now);

        return $now;
    }

    private function battle(string $status = 'active', array $challenge = []): Challenge
    {
        $battle = Battle::create([
            'title' => 'Tong dueli',
            'status' => $status,
            'period_days' => 10,
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-30',
            'timezone' => config('telegram.timezone'),
            'created_by' => $this->me->id,
            'invite_token' => Str::random(12),
        ]);
        BattleParticipant::create(['battle_id' => $battle->id, 'user_id' => $this->me->id, 'accepted' => true]);
        BattleParticipant::create(['battle_id' => $battle->id, 'user_id' => $this->rival->id, 'accepted' => true]);

        return Challenge::create(array_merge([
            'battle_id' => $battle->id,
            'template_key' => 'earlyRise',
            'name' => '',
            'icon' => '🌅',
            'cadence' => Cadence::Daily,
            'weekdays' => [],
            'remind_at' => '07:00',
            'start_date' => '2026-10-15',
            'pending' => false,
        ], $challenge));
    }

    private function reminders(): ReminderService
    {
        return app(ReminderService::class);
    }

    // --- Odat vaqti --------------------------------------------------------

    public function test_habit_reminder_goes_to_both_players_once_at_its_time(): void
    {
        $challenge = $this->battle();

        $this->reminders()->habit($this->at('2026-10-20 06:59'));
        $this->assertSame([], $this->sent, 'vaqtidan oldin — yo\'q');

        $now = $this->at('2026-10-20 07:00');
        $this->assertSame(2, $this->reminders()->habit($now));
        $this->assertSame(0, $this->reminders()->habit($this->at('2026-10-20 07:01')), 'takror yo\'q');

        $this->assertEqualsCanonicalizing([8001, 8002], array_column($this->sent, 'to'));
        $this->assertStringContainsString('07:00', $this->sent[0]['text']);
        $this->assertStringContainsString('Erta turish', $this->sent[0]['text']);
        $this->assertSame("/battle/{$challenge->battle_id}", $this->sent[0]['path']);
    }

    public function test_habit_reminder_skips_who_already_sent_proof(): void
    {
        $challenge = $this->battle();
        $this->at('2026-10-20 06:50');
        Completion::create([
            'challenge_id' => $challenge->id, 'user_id' => $this->me->id,
            'day' => '2026-10-20', 'status' => CompletionStatus::Pending, 'file_id' => 'x',
        ]);

        $this->reminders()->habit($this->at('2026-10-20 07:00'));

        $this->assertSame([8002], array_column($this->sent, 'to'));
    }

    public function test_late_cron_still_delivers_within_the_window_but_not_after(): void
    {
        $this->battle();

        $this->assertSame(2, $this->reminders()->habit($this->at('2026-10-20 08:30')), 'cron kechikdi — baribir yetadi');

        $this->sent = [];
        $this->assertSame(0, $this->reminders()->habit($this->at('2026-10-21 09:30')), 'oynadan keyin — yo\'q');
    }

    public function test_habit_without_time_or_in_negotiation_is_silent(): void
    {
        $this->battle(challenge: ['remind_at' => null]);
        $this->battle(status: BattleStatus::Negotiating->value);

        $this->assertSame(0, $this->reminders()->habit($this->at('2026-10-20 07:00')));
    }

    public function test_quest_reminder_goes_only_to_the_owner(): void
    {
        $quest = Quest::create([
            'title' => 'Missiya', 'status' => QuestStatus::Active,
            'owner_id' => $this->me->id, 'witness_id' => $this->rival->id,
            'period_days' => 10, 'start_date' => '2026-10-15', 'end_date' => '2026-10-30',
            'timezone' => config('telegram.timezone'), 'goal_percent' => 80,
            'invite_token' => Str::random(12),
        ]);
        Challenge::create([
            'quest_id' => $quest->id, 'name' => 'Kitob', 'icon' => '📖',
            'cadence' => Cadence::Daily, 'weekdays' => [], 'remind_at' => '21:30',
            'start_date' => '2026-10-15', 'pending' => false,
        ]);

        $this->reminders()->habit($this->at('2026-10-20 21:30'));

        $this->assertSame([8001], array_column($this->sent, 'to'));
        $this->assertSame("/quest/{$quest->id}", $this->sent[0]['path']);
    }

    // --- Kechki va ertalabki -------------------------------------------------

    public function test_evening_digest_lists_unsent_habits_and_skips_finished_players(): void
    {
        $challenge = $this->battle(challenge: ['remind_at' => null]);
        $this->at('2026-10-20 20:00');
        Completion::create([
            'challenge_id' => $challenge->id, 'user_id' => $this->rival->id,
            'day' => '2026-10-20', 'status' => CompletionStatus::Approved, 'file_id' => 'x',
        ]);

        $this->assertSame(0, $this->reminders()->evening($this->at('2026-10-20 22:59')));

        // 23:00 dan keyin, lekin oyna ichida (yarim tundan keyin ham — o'sha kun)
        $this->assertSame(1, $this->reminders()->evening($this->at('2026-10-21 00:30')));
        $this->assertSame(0, $this->reminders()->evening($this->at('2026-10-21 00:40')), 'takror yo\'q');

        $this->assertSame([8001], array_column($this->sent, 'to'));
        $this->assertStringContainsString('Erta turish', $this->sent[0]['text']);
    }

    public function test_morning_reminder_lists_yesterdays_forgotten_proofs(): void
    {
        $this->battle(challenge: ['remind_at' => null]);

        $this->assertSame(2, $this->reminders()->late($this->at('2026-10-21 10:05')));
        $this->assertStringContainsString('Kecha', $this->sent[0]['text']);
    }

    public function test_habit_time_can_be_set_and_cleared_via_api(): void
    {
        $challenge = $this->battle(challenge: ['remind_at' => null]);
        $url = "/api/battles/{$challenge->battle_id}/challenges/{$challenge->id}";
        $as = fn () => $this->withHeader('X-Dev-Telegram-Id', '8001');

        $as()->patchJson($url, ['cadence' => 'daily', 'remind_at' => '06:30'])->assertStatus(200);
        $this->assertSame('06:30', $challenge->fresh()->remind_at);

        $as()->patchJson($url, ['cadence' => 'daily', 'remind_at' => '25:00'])->assertStatus(422);

        // Boshqa maydonni tahrirlash vaqtni o'chirib yubormaydi
        $as()->patchJson($url, ['cadence' => 'daily', 'name' => 'Tong'])->assertStatus(200);
        $this->assertSame('06:30', $challenge->fresh()->remind_at);

        $as()->patchJson($url, ['cadence' => 'daily', 'remind_at' => null])->assertStatus(200);
        $this->assertNull($challenge->fresh()->remind_at);
    }

    public function test_run_executes_all_three_kinds(): void
    {
        $this->battle();

        $result = $this->reminders()->run($this->at('2026-10-20 07:00'));

        $this->assertSame(['habit' => 2, 'evening' => 0, 'late' => 0], $result);
    }
}
