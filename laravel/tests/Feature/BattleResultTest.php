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
use App\Support\Clock;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Duel yakuni — g'olibni aniqlash va natijani ko'rsatish (SPEC §6).
 *
 * Kalit talab: natija cron'ga BOG'LIQ EMAS. Cron kechiksa yoki umuman
 * sozlanmagan bo'lsa ham, davri tugagan duel ochilganda yopiladi.
 */
class BattleResultTest extends TestCase
{
    use RefreshDatabase;

    private const TG_ME = 4001;

    private const TG_RIVAL = 4002;

    private function asTg(int $telegramId): self
    {
        return $this->withHeader('X-Dev-Telegram-Id', (string) $telegramId);
    }

    /**
     * Davri KECHA tugagan, lekin hali `active` turgan duel yaratadi —
     * ya'ni cron hali yugurmagan holat.
     *
     * @return array{battle: Battle, me: User, rival: User}
     */
    private function expiredBattle(int $myDays, int $rivalDays): array
    {
        $me = User::create(['telegram_id' => self::TG_ME, 'first_name' => 'Men']);
        $rival = User::create(['telegram_id' => self::TG_RIVAL, 'first_name' => 'Raqib']);

        $today = Clock::todayLocal();
        $start = $today->subDays(8);

        $battle = Battle::create([
            'title' => 'Tugagan duel',
            'status' => BattleStatus::Active,   // cron hali tegmagan
            'period_days' => 7,
            'start_date' => $start->toDateString(),
            'end_date' => $today->subDay()->toDateString(),
            'timezone' => config('telegram.timezone'),
            'created_by' => $me->id,
            'invite_token' => Str::random(12),
        ]);

        BattleParticipant::create(['battle_id' => $battle->id, 'user_id' => $me->id, 'accepted' => true]);
        BattleParticipant::create(['battle_id' => $battle->id, 'user_id' => $rival->id, 'accepted' => true]);

        $challenge = Challenge::create([
            'battle_id' => $battle->id,
            'name' => 'Sport',
            'icon' => '🏃',
            'cadence' => Cadence::Daily,
            'weekdays' => [],
            'start_date' => $start->toDateString(),
            'pending' => false,
        ]);

        $fill = function (int $userId, int $count) use ($challenge, $start) {
            for ($d = 0; $d < $count; $d++) {
                Completion::create([
                    'challenge_id' => $challenge->id,
                    'user_id' => $userId,
                    'day' => $start->addDays($d)->toDateString(),
                    'status' => CompletionStatus::Approved,
                    'submitted_at' => now(),
                    'resolved_at' => now(),
                    'file_id' => 'test',
                ]);
            }
        };

        $fill($me->id, $myDays);
        $fill($rival->id, $rivalDays);

        return ['battle' => $battle, 'me' => $me, 'rival' => $rival];
    }

    public function test_expired_battle_closes_on_open_without_waiting_for_cron(): void
    {
        ['battle' => $battle] = $this->expiredBattle(myDays: 6, rivalDays: 3);

        $this->assertSame(BattleStatus::Active, $battle->status, 'boshida ochiq');

        $this->asTg(self::TG_ME)
            ->getJson("/api/battles/{$battle->id}")
            ->assertStatus(200);

        $this->assertSame(
            BattleStatus::Finished,
            $battle->fresh()->status,
            'ochilganda yopilishi kerak — cron kutilmaydi',
        );
    }

    public function test_winner_is_the_higher_score_and_result_is_reported_from_each_side(): void
    {
        ['battle' => $battle, 'me' => $me] = $this->expiredBattle(myDays: 6, rivalDays: 3);

        // Yutgan tomon
        $mine = $this->asTg(self::TG_ME)
            ->getJson("/api/battles/{$battle->id}")
            ->assertStatus(200)
            ->assertJsonPath('result.you_won', true)
            ->assertJsonPath('result.is_draw', false);

        $this->assertSame($me->id, $mine->json('result.winner.id'));

        // Yutqazgan tomon — AYNAN SHU duel, boshqa nuqtai nazardan
        $this->asTg(self::TG_RIVAL)
            ->getJson("/api/battles/{$battle->id}")
            ->assertStatus(200)
            ->assertJsonPath('result.you_won', false)
            ->assertJsonPath('result.is_draw', false)
            ->assertJsonPath('result.winner.id', $me->id);

        $this->assertSame($me->id, $battle->fresh()->winner_id);
    }

    public function test_equal_scores_and_equal_completions_are_a_draw(): void
    {
        ['battle' => $battle] = $this->expiredBattle(myDays: 4, rivalDays: 4);

        $this->asTg(self::TG_ME)
            ->getJson("/api/battles/{$battle->id}")
            ->assertStatus(200)
            ->assertJsonPath('result.is_draw', true)
            ->assertJsonPath('result.you_won', false)
            ->assertJsonPath('result.winner', null);

        $this->assertNull($battle->fresh()->winner_id);
    }

    public function test_active_battle_has_no_result_yet(): void
    {
        $me = User::create(['telegram_id' => self::TG_ME, 'first_name' => 'Men']);
        $today = Clock::todayLocal();

        $battle = Battle::create([
            'title' => 'Davom etyapti',
            'status' => BattleStatus::Active,
            'period_days' => 7,
            'start_date' => $today->toDateString(),
            'end_date' => $today->addDays(6)->toDateString(),
            'timezone' => config('telegram.timezone'),
            'created_by' => $me->id,
            'invite_token' => Str::random(12),
        ]);
        BattleParticipant::create(['battle_id' => $battle->id, 'user_id' => $me->id, 'accepted' => true]);

        $this->asTg(self::TG_ME)
            ->getJson("/api/battles/{$battle->id}")
            ->assertStatus(200)
            ->assertJsonPath('result', null);

        $this->assertSame(BattleStatus::Active, $battle->fresh()->status);
    }

    public function test_final_score_is_sealed_on_the_participant_row(): void
    {
        // `battle_participants.score` faqat cron'da yangilanardi; yopish
        // vaqtida ballni muhrlamasak, arxivdagi hisob 0 bo'lib qolardi.
        ['battle' => $battle, 'me' => $me] = $this->expiredBattle(myDays: 6, rivalDays: 3);

        $this->asTg(self::TG_ME)->getJson("/api/battles/{$battle->id}")->assertStatus(200);

        $row = BattleParticipant::where('battle_id', $battle->id)
            ->where('user_id', $me->id)
            ->firstOrFail();

        $this->assertGreaterThan(0, $row->score, 'yakuniy ball saqlanishi kerak');
    }

    public function test_profile_stats_count_the_win(): void
    {
        ['battle' => $battle] = $this->expiredBattle(myDays: 6, rivalDays: 3);

        // Yopilishi uchun bir marta ochamiz
        $this->asTg(self::TG_ME)->getJson("/api/battles/{$battle->id}");

        $this->asTg(self::TG_ME)
            ->getJson('/api/stats')
            ->assertStatus(200)
            ->assertJsonPath('battles.won', 1)
            ->assertJsonPath('battles.lost', 0)
            ->assertJsonPath('battles.finished', 1);

        $this->asTg(self::TG_RIVAL)
            ->getJson('/api/stats')
            ->assertStatus(200)
            ->assertJsonPath('battles.won', 0)
            ->assertJsonPath('battles.lost', 1);
    }

    public function test_stats_activity_covers_thirty_days(): void
    {
        $this->expiredBattle(myDays: 6, rivalDays: 3);

        $activity = $this->asTg(self::TG_ME)
            ->getJson('/api/stats')
            ->assertStatus(200)
            ->json('activity');

        $this->assertCount(30, $activity);
        $this->assertSame(
            Clock::todayLocal()->toDateString(),
            end($activity)['date'],
            'oxirgi katak bugun bo`lishi kerak',
        );
    }
}
