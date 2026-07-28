<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\BattleStatus;
use App\Enums\Cadence;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\User;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class BattleService
{
    public function __construct(private readonly ScoringService $scoring) {}

    /**
     * @param  array<int, array{template_key: ?string, name: string, icon: string, cadence: string, weekdays: array<int>}>  $challenges
     */
    public function create(User $user, string $title, int $periodDays, bool $startTomorrow, array $challenges): Battle
    {
        $start = Clock::todayLocal()->addDays($startTomorrow ? 1 : 0);
        $end = $start->addDays($periodDays);

        $battle = Battle::create([
            'title' => $title,
            'status' => BattleStatus::Pending,
            'period_days' => $periodDays,
            'start_date' => $start->toDateString(),
            'end_date' => $end->toDateString(),
            'timezone' => config('telegram.timezone'),
            'created_by' => $user->id,
            'invite_token' => Str::random(12),
        ]);

        $battle->participants()->create(['user_id' => $user->id, 'accepted' => true]);

        foreach ($challenges as $data) {
            $battle->challenges()->create([
                'template_key' => $data['template_key'] ?? null,
                'name' => $data['name'] ?? '',
                'icon' => $data['icon'] ?? '🎯',
                'cadence' => $data['cadence'] ?? Cadence::Daily->value,
                'weekdays' => $data['weekdays'] ?? [],
                'start_date' => $start->toDateString(),
            ]);
        }

        return $battle->fresh();
    }

    public function acceptByToken(User $user, string $token): Battle
    {
        $battle = Battle::where('invite_token', $token)->firstOrFail();

        BattleParticipant::firstOrCreate(
            ['battle_id' => $battle->id, 'user_id' => $user->id],
            ['accepted' => true],
        );

        $battle->update(['status' => BattleStatus::Active]);

        return $battle;
    }

    /**
     * Foydalanuvchining battle'lari + hisob + qolgan vaqt (Home ekrani).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(User $user): array
    {
        $battleIds = BattleParticipant::where('user_id', $user->id)->pluck('battle_id');
        $battles = Battle::with(['challenges', 'participants.user'])->whereIn('id', $battleIds)->get();

        return $battles->map(fn (Battle $battle) => [
            'battle' => $battle,
            'players' => $this->players($battle, $user->id),
            ...$this->timeLeft($battle),
        ])->all();
    }

    /**
     * Battle detali — leaderboard + challenge'lar.
     *
     * @return array<string, mixed>
     */
    public function detail(Battle $battle, User $me): array
    {
        $battle->loadMissing(['challenges', 'participants.user']);

        return [
            'battle' => $battle,
            'players' => $this->players($battle, $me->id, withBreakdown: true),
            'challenges' => $battle->challenges,
        ];
    }

    /**
     * Bugun kutilayotgan challenge'lar (foydalanuvchi uchun) + holati.
     *
     * @return array<int, array{challenge: Challenge, status: ?string}>
     */
    public function todayTasks(Battle $battle, User $user): array
    {
        $today = Clock::todayLocal();
        $out = [];

        foreach ($battle->challenges()->where('active', true)->get() as $challenge) {
            // Sana bo'yicha taqqoslash (timezone-instant emas)
            $startsAfterToday = $challenge->start_date->toDateString() > $today->toDateString();
            if ($startsAfterToday || ! $challenge->cadence->isDue($today, $challenge->weekdaysList())) {
                continue;
            }

            $completion = Completion::where('challenge_id', $challenge->id)
                ->where('user_id', $user->id)
                ->whereDate('day', $today->toDateString())
                ->first();

            $out[] = ['challenge' => $challenge, 'status' => $completion?->status->value];
        }

        return $out;
    }

    /**
     * @return array<int, array{user: User, score: float, is_me: bool, breakdown?: array<int, int>}>
     */
    private function players(Battle $battle, int $meId, bool $withBreakdown = false): array
    {
        return $battle->participants->map(function (BattleParticipant $participant) use ($battle, $meId, $withBreakdown) {
            $result = $this->scoring->forParticipant($battle, $participant->user_id);
            $row = [
                'user' => $participant->user,
                'score' => $result['score'],
                'is_me' => $participant->user_id === $meId,
            ];
            if ($withBreakdown) {
                $row['breakdown'] = $result['breakdown'];
            }

            return $row;
        })->all();
    }

    /**
     * @return array{days_left: int, hours_left: int}
     */
    private function timeLeft(Battle $battle): array
    {
        $end = CarbonImmutable::parse($battle->end_date->toDateString(), config('telegram.timezone'))->addDay();
        $seconds = max(0, Clock::nowLocal()->diffInSeconds($end, false));

        return [
            'days_left' => intdiv((int) $seconds, 86400),
            'hours_left' => intdiv((int) $seconds % 86400, 3600),
        ];
    }
}
