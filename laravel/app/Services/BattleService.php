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
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly BattleAccess $access,
        private readonly \App\Services\Telegram\NotificationService $notifications,
    ) {}

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
                'description' => $data['description'] ?? null,
                'icon' => $data['icon'] ?? '🎯',
                'cadence' => $data['cadence'] ?? Cadence::Daily->value,
                'weekdays' => $data['weekdays'] ?? [],
                'proof_type' => $data['proof_type'] ?? 'camera',
                'start_date' => $start->toDateString(),
                // battle boshidagilar — ikkalasi qabul qilgan (pending emas)
            ]);
        }

        return $battle->fresh();
    }

    /**
     * Mavjud battle'ga yangi challenge qo'shadi (SPEC §19–20) — o'sha kundan kuchga kiradi.
     *
     * @param  array{template_key: ?string, name: string, icon: string, cadence: string, weekdays: array<int>}  $data
     */
    public function addChallenge(User $user, Battle $battle, array $data): Challenge
    {
        $start = ($data['start_tomorrow'] ?? false)
            ? Clock::todayLocal()->addDay()
            : Clock::todayLocal();

        $challenge = $battle->challenges()->create([
            'template_key' => $data['template_key'] ?? null,
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? '🎯',
            'cadence' => $data['cadence'] ?? Cadence::Daily->value,
            'weekdays' => $data['weekdays'] ?? [],
            'proof_type' => $data['proof_type'] ?? 'camera',
            'start_date' => $start->toDateString(),
            'pending' => true,             // taklif — do'st qabul qilmaguncha
            'proposed_by' => $user->id,
        ]);

        $label = $this->challengeLabel($challenge, $data['template_key'] ?? null);
        $desc = $challenge->description ? "\n<i>{$challenge->description}</i>" : '';
        $this->notifications->notify(
            $this->access->otherTelegramIds($battle->id, $user->id),
            "🆕 <b>{$user->first_name}</b> yangi challenge taklif qildi:\n"
                ."{$challenge->icon} <b>{$label}</b>{$desc}\n\nQabul qilasanmi? Ilovada ko'r 👇",
        );

        return $challenge;
    }

    /**
     * Taklif qilingan challenge'ni qabul qiladi (do'st) — o'sha kundan boshlanadi.
     */
    public function acceptChallenge(User $user, Battle $battle, int $challengeId): Challenge
    {
        if (! $this->access->isParticipant($battle->id, $user->id)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException("Ruxsat yo'q");
        }

        $challenge = $battle->challenges()->where('pending', true)->findOrFail($challengeId);
        if ($challenge->proposed_by === $user->id) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                "O'z taklifingni qabul qila olmaysan",
            );
        }

        // Taklif paytida tanlangan boshlanish sanasini saqlaymiz (ertaga bo'lsa ertaga),
        // faqat o'tib ketgan bo'lsa bugundan boshlaymiz.
        $today = Clock::todayLocal();
        $start = $challenge->start_date->toDateString() > $today->toDateString()
            ? $challenge->start_date->toDateString()
            : $today->toDateString();

        $challenge->update([
            'pending' => false,
            'start_date' => $start,
        ]);

        $proposer = User::find($challenge->proposed_by);
        if ($proposer) {
            $this->notifications->notify(
                [$proposer->telegram_id],
                "✅ <b>{$user->first_name}</b> challenge'ni qabul qildi: {$challenge->icon} "
                    .$this->challengeLabel($challenge, $challenge->template_key)."\nBoshlandi! 🔥",
            );
        }

        return $challenge;
    }

    /**
     * Taklifni rad etadi (challenge o'chiriladi).
     */
    public function rejectChallenge(User $user, Battle $battle, int $challengeId): void
    {
        if (! $this->access->isParticipant($battle->id, $user->id)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException("Ruxsat yo'q");
        }

        $challenge = $battle->challenges()->where('pending', true)->findOrFail($challengeId);
        if ($challenge->proposed_by === $user->id) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException("Ruxsat yo'q");
        }

        $proposer = User::find($challenge->proposed_by);
        $label = $this->challengeLabel($challenge, $challenge->template_key);
        $challenge->delete();

        if ($proposer) {
            $this->notifications->notify(
                [$proposer->telegram_id],
                "❌ <b>{$user->first_name}</b> challenge taklifini rad etdi: {$label}",
            );
        }
    }

    private function challengeLabel(Challenge $challenge, ?string $templateKey): string
    {
        return $challenge->name !== '' ? $challenge->name : ($templateKey ?? 'challenge');
    }

    /**
     * Taklif ko'rinishi (qabul qilishdan oldin) — token bo'yicha.
     *
     * @return array<string, mixed>
     */
    public function invitePreview(User $user, string $token): array
    {
        $battle = Battle::with(['challenges', 'participants.user'])
            ->where('invite_token', $token)
            ->firstOrFail();

        return [
            'battle' => $battle,
            'challenges' => $battle->challenges,
            'creator' => User::find($battle->created_by),
            'already_participant' => $battle->participants->contains('user_id', $user->id),
        ];
    }

    /**
     * Battle sarlavhasini tahrirlaydi (faqat yaratuvchi).
     */
    public function updateBattle(User $user, Battle $battle, string $title): Battle
    {
        if ($battle->created_by !== $user->id) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                "Faqat yaratuvchi tahrirlay oladi",
            );
        }

        $battle->update(['title' => $title]);

        return $battle;
    }

    /**
     * Challenge'ni tahrirlaydi (ishtirokchi).
     *
     * @param  array{name?: string, icon?: string, cadence?: string, weekdays?: array<int>}  $data
     */
    public function updateChallenge(User $user, Battle $battle, int $challengeId, array $data): Challenge
    {
        if (! $this->access->isParticipant($battle->id, $user->id)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException("Ruxsat yo'q");
        }

        $challenge = $battle->challenges()->findOrFail($challengeId);
        $challenge->update([
            'name' => $data['name'] ?? $challenge->name,
            'description' => array_key_exists('description', $data)
                ? ($data['description'] ?: null)
                : $challenge->description,
            'icon' => $data['icon'] ?? $challenge->icon,
            'cadence' => $data['cadence'] ?? $challenge->cadence->value,
            'weekdays' => $data['weekdays'] ?? $challenge->weekdays,
            'proof_type' => $data['proof_type'] ?? $challenge->proof_type->value,
        ]);

        return $challenge;
    }

    /**
     * Battle'ni o'chiradi (faqat yaratuvchi) — bog'liq hammasi cascade bilan.
     */
    public function deleteBattle(User $user, Battle $battle): void
    {
        if ($battle->created_by !== $user->id) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                "Faqat yaratuvchi o'chira oladi",
            );
        }

        $battle->delete();
    }

    /**
     * Battle'dan bitta challenge'ni o'chiradi (ishtirokchi).
     */
    public function deleteChallenge(User $user, Battle $battle, int $challengeId): void
    {
        if (! $this->access->isParticipant($battle->id, $user->id)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException("Ruxsat yo'q");
        }

        $battle->challenges()->findOrFail($challengeId)->delete();
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

        foreach ($battle->challenges()->where('active', true)->where('pending', false)->get() as $challenge) {
            // Sana bo'yicha taqqoslash (timezone-instant emas)
            $startsAfterToday = $challenge->start_date->toDateString() > $today->toDateString();
            if ($startsAfterToday || ! $challenge->cadence->isDue($today, $challenge->weekdaysList())) {
                continue;
            }

            $completion = Completion::where('challenge_id', $challenge->id)
                ->where('user_id', $user->id)
                ->whereDate('day', $today->toDateString())
                ->first();

            $out[] = [
                'challenge' => $challenge,
                'status' => $completion?->status->value,
                'completion_id' => $completion?->id,
            ];
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
