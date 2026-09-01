<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Cadence;
use App\Enums\QuestRole;
use App\Enums\QuestStatus;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Quest;
use App\Models\User;
use App\Services\Telegram\NotificationService;
use App\Support\Clock;
use App\Support\DateRange;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Missiya — yakka odat yo'li, tashqi guvoh bilan.
 *
 * Duelda challenge ikkalasiga tegishli. Missiyada esa odat FAQAT eganiki:
 * guvohga o'sha odat kerak emas, u faqat isbotni tekshiradi.
 */
class QuestService
{
    public function __construct(
        private readonly QuestStatsService $stats,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $challenges
     */
    public function create(
        User $owner,
        string $title,
        DateRange $period,
        int $goalPercent,
        array $challenges,
    ): Quest {
        $start = $period->start;

        $quest = Quest::create([
            'title' => $title,
            'status' => QuestStatus::Active,
            'owner_id' => $owner->id,
            'witness_id' => null,
            // Sanalardan hosila — ikki manba bo'lsa ajralib ketardi
            'period_days' => $period->days(),
            'start_date' => $period->startDate(),
            'end_date' => $period->endDate(),
            'timezone' => config('telegram.timezone'),
            'goal_percent' => $goalPercent,
            'invite_token' => Str::random(12),
        ]);

        foreach ($challenges as $data) {
            $quest->challenges()->create($this->challengeAttributes($data, $period->startDate()));
        }

        return $quest->fresh(['challenges']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function challengeAttributes(array $data, string $startDate): array
    {
        return [
            'battle_id' => null,
            'template_key' => $data['template_key'] ?? null,
            'name' => $data['name'] ?? '',
            'description' => $data['description'] ?? null,
            'icon' => $data['icon'] ?? '🎯',
            'cadence' => $data['cadence'] ?? Cadence::Daily->value,
            'weekdays' => $data['weekdays'] ?? [],
            'proof_type' => $data['proof_type'] ?? 'camera',
            'start_date' => $startDate,
            // Missiya challenge'lari kelishuvni talab qilmaydi — odat eganiki,
            // guvohning roziligi shart emas (duelda esa shart).
            'pending' => false,
        ];
    }

    // --- Guvoh taklifi ------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function invitePreview(User $viewer, string $token): array
    {
        $quest = Quest::with(['challenges', 'owner', 'witness'])
            ->where('invite_token', $token)
            ->firstOrFail();

        return [
            'quest' => $quest,
            'challenges' => $quest->challenges->where('pending', false)->values(),
            'owner' => $quest->owner,
            'role' => $quest->roleOf($viewer->id)?->value,
            'taken' => $quest->hasWitness() && $quest->witness_id !== $viewer->id,
            'is_owner' => $quest->owner_id === $viewer->id,
        ];
    }

    /**
     * Guvoh bo'lib qo'shilish. Guvoh — aynan BITTA va u ega bo'la olmaydi.
     */
    public function acceptWitness(User $user, string $token): Quest
    {
        $quest = Quest::with('owner')->where('invite_token', $token)->firstOrFail();

        if ($quest->owner_id === $user->id) {
            throw new ConflictHttpException("O'z missiyangga o'zing guvoh bo'la olmaysan");
        }
        if ($quest->witness_id === $user->id) {
            return $quest;   // idempotent — havolani ikki marta bossa
        }
        if ($quest->hasWitness()) {
            throw new ConflictHttpException('Bu missiyaning guvohi allaqachon bor');
        }
        if (! $quest->status->isOpen()) {
            throw new ConflictHttpException('Missiya yakunlangan');
        }

        $quest->update(['witness_id' => $user->id]);

        $this->notifications->notify(
            [$quest->owner->telegram_id],
            "👁 <b>{$user->first_name}</b> missiyangga guvoh bo'ldi: <b>{$quest->title}</b>\n"
                .'Endi isbotlaringni u tekshiradi. Omad! 🔥',
        );

        return $quest->fresh(['owner', 'witness']);
    }

    // --- Ro'yxat va detal ---------------------------------------------------

    /**
     * Foydalanuvchining missiyalari — ega VA guvoh sifatida.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForUser(User $user): array
    {
        $quests = Quest::with(['challenges', 'owner', 'witness'])
            ->where('owner_id', $user->id)
            ->orWhere('witness_id', $user->id)
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [QuestStatus::Active->value])
            ->orderByDesc('id')
            ->get();

        return $quests->map(function (Quest $quest) use ($user) {
            $report = $this->stats->report($quest);

            return [
                'quest' => $quest,
                'role' => $quest->roleOf($user->id)?->value,
                'owner' => $quest->owner,
                'witness' => $quest->witness,
                'rate' => $report->rate,
                'current_streak' => $report->currentStreak,
                'done' => $report->done,
                'planned' => $report->planned,
                'goal_reachable' => $report->goalReachable,
                ...$this->timeLeft($quest),
            ];
        })->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Quest $quest, User $viewer): array
    {
        $this->assertMember($quest, $viewer);

        $quest->loadMissing(['challenges', 'owner', 'witness']);

        return [
            'quest' => $quest,
            'role' => $quest->roleOf($viewer->id)?->value,
            'owner' => $quest->owner,
            'witness' => $quest->witness,
            'challenges' => $quest->challenges->where('pending', false)->values(),
            'stats' => $this->stats->report($quest),
            ...$this->timeLeft($quest),
        ];
    }

    /**
     * Bugun egadan kutilayotgan ishlar. Guvoh uchun bo'sh — u bajarmaydi.
     *
     * @return array<int, array<string, mixed>>
     */
    public function todayTasks(Quest $quest, User $user): array
    {
        $this->assertMember($quest, $user);

        if (! $quest->canSubmit($user->id) || ! $quest->status->isOpen()) {
            return [];
        }

        $today = Clock::todayLocal();
        $todayStr = $today->toDateString();

        if ($todayStr < $quest->start_date->toDateString() || $todayStr > $quest->end_date->toDateString()) {
            return [];
        }

        $challenges = $quest->challenges()
            ->where('active', true)
            ->where('pending', false)
            ->get();

        $completions = Completion::query()
            ->whereIn('challenge_id', $challenges->pluck('id'))
            ->where('user_id', $user->id)
            ->whereDate('day', $todayStr)
            ->get()
            ->keyBy('challenge_id');

        $out = [];
        foreach ($challenges as $challenge) {
            if ($challenge->start_date->toDateString() > $todayStr) {
                continue;
            }
            if (! $challenge->cadence->isDue($today, $challenge->weekdaysList())) {
                continue;
            }

            /** @var Completion|null $completion */
            $completion = $completions->get($challenge->id);

            $out[] = [
                'challenge' => $challenge,
                'status' => $completion?->status->value,
                'completion_id' => $completion?->id,
            ];
        }

        return $out;
    }

    // --- Tahrirlash ---------------------------------------------------------

    public function update(User $user, Quest $quest, string $title, ?int $goalPercent): Quest
    {
        $this->assertOwner($quest, $user);

        $quest->update(array_filter([
            'title' => $title,
            'goal_percent' => $goalPercent,
        ], fn ($v) => $v !== null));

        return $quest->fresh();
    }

    public function delete(User $user, Quest $quest): void
    {
        $this->assertOwner($quest, $user);

        $quest->delete();
    }

    /**
     * Yarim yo'lda to'xtatish. Duelning "forfeit"idan farqli — bu yerda
     * yutqazadigan raqib yo'q, shuning uchun jazo ham yo'q.
     */
    public function abandon(User $user, Quest $quest): Quest
    {
        $this->assertOwner($quest, $user);

        if (! $quest->status->isOpen()) {
            throw new ConflictHttpException('Missiya allaqachon yakunlangan');
        }

        $quest->update(['status' => QuestStatus::Abandoned]);

        if ($quest->witness_id !== null) {
            $this->notifications->notify(
                $quest->memberTelegramIds($user->id),
                "🚪 <b>{$user->first_name}</b> «{$quest->title}» missiyasini to'xtatdi.",
            );
        }

        return $quest->fresh();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function addChallenge(User $user, Quest $quest, array $data): Challenge
    {
        $this->assertOwner($quest, $user);

        $start = ($data['start_tomorrow'] ?? false)
            ? Clock::todayLocal()->addDay()
            : Clock::todayLocal();

        $challenge = $quest->challenges()->create(
            $this->challengeAttributes($data, $start->toDateString()),
        );

        if ($quest->witness_id !== null) {
            $label = $challenge->name !== '' ? $challenge->name : ($challenge->template_key ?? 'challenge');
            $this->notifications->notify(
                $quest->memberTelegramIds($user->id),
                "🆕 <b>{$user->first_name}</b> missiyasiga yangi odat qo'shdi: "
                    ."{$challenge->icon} <b>{$label}</b>",
            );
        }

        return $challenge;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateChallenge(User $user, Quest $quest, int $challengeId, array $data): Challenge
    {
        $this->assertOwner($quest, $user);

        $challenge = $quest->challenges()->findOrFail($challengeId);
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

    public function deleteChallenge(User $user, Quest $quest, int $challengeId): void
    {
        $this->assertOwner($quest, $user);

        $quest->challenges()->findOrFail($challengeId)->delete();
    }

    // --- Yordamchilar -------------------------------------------------------

    private function assertMember(Quest $quest, User $user): void
    {
        if (! $quest->hasMember($user->id)) {
            throw new AccessDeniedHttpException('Bu missiya sizga ochiq emas');
        }
    }

    private function assertOwner(Quest $quest, User $user): void
    {
        if ($quest->roleOf($user->id) !== QuestRole::Owner) {
            throw new AccessDeniedHttpException('Faqat missiya egasi o\'zgartira oladi');
        }
    }

    /**
     * @return array{days_left: int, hours_left: int}
     */
    private function timeLeft(Quest $quest): array
    {
        $end = CarbonImmutable::parse($quest->end_date->toDateString(), config('telegram.timezone'))->addDay();
        $seconds = max(0, Clock::nowLocal()->diffInSeconds($end, false));

        return [
            'days_left' => intdiv((int) $seconds, 86400),
            'hours_left' => intdiv((int) $seconds % 86400, 3600),
        ];
    }
}
