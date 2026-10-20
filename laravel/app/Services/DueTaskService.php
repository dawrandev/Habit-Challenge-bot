<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ProofContext;
use App\Enums\BattleStatus;
use App\Enums\CompletionStatus;
use App\Enums\QuestStatus;
use App\Models\Battle;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Quest;
use App\Models\User;
use App\Support\Clock;
use App\Support\LateWindow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * "Kim, qaysi kuni, nimani bajarishi kerak" — duel va missiya uchun umumiy.
 *
 * Kechagi hisobotlar ro'yxati va eslatmalar (SPEC §18) shu bitta manbadan
 * oladi: ikki joyda alohida hisoblansa, eslatma bir narsani, ekran boshqasini
 * aytib qolardi.
 */
class DueTaskService
{
    /**
     * Foydalanuvchi isbot yubora oladigan ochiq duel va missiyalar.
     *
     * @return Collection<int, Battle|Quest>
     */
    public function containersFor(User $user): Collection
    {
        $battles = Battle::query()
            ->whereIn('status', [BattleStatus::Active->value, BattleStatus::Pending->value])
            ->whereHas('participants', fn ($q) => $q->where('user_id', $user->id))
            ->get()
            ->filter(fn (Battle $b) => $b->acceptsProofs());

        $quests = Quest::query()
            ->where('status', QuestStatus::Active->value)
            ->where('owner_id', $user->id)
            ->get();

        return $battles->concat($quests)->values();
    }

    /**
     * Ochiq duel/missiyasi bor foydalanuvchilar (eslatma nomzodlari).
     *
     * @return Collection<int, User>
     */
    public function activeMembers(): Collection
    {
        $battleUserIds = Battle::query()
            ->where('status', BattleStatus::Active->value)
            ->with('participants:id,battle_id,user_id')
            ->get()
            ->flatMap(fn (Battle $b) => $b->participants->pluck('user_id'));

        $questOwnerIds = Quest::query()
            ->where('status', QuestStatus::Active->value)
            ->pluck('owner_id');

        return User::query()
            ->whereIn('id', $battleUserIds->concat($questOwnerIds)->unique()->values())
            ->whereNotNull('telegram_id')
            ->get();
    }

    /**
     * Berilgan kunda foydalanuvchidan kutilgan odatlar va holati.
     *
     * @return list<array{context: ProofContext, challenge: Challenge, day: string, status: ?CompletionStatus, completion_id: ?int}>
     */
    public function tasks(User $user, string $day): array
    {
        $date = CarbonImmutable::parse($day);
        $out = [];

        foreach ($this->containersFor($user) as $context) {
            if (! $context->coversDay($day) || ! $context->canSubmit($user->id)) {
                continue;
            }

            $challenges = $context->challenges()
                ->where('active', true)
                ->where('pending', false)
                ->whereDate('start_date', '<=', $day)
                ->get()
                ->filter(fn (Challenge $c) => $c->cadence->isDue($date, $c->weekdaysList()));

            $completions = Completion::query()
                ->whereIn('challenge_id', $challenges->pluck('id'))
                ->where('user_id', $user->id)
                ->whereDate('day', $day)
                ->get()
                ->keyBy('challenge_id');

            foreach ($challenges as $challenge) {
                /** @var Completion|null $completion */
                $completion = $completions->get($challenge->id);

                $out[] = [
                    'context' => $context,
                    'challenge' => $challenge,
                    'day' => $day,
                    'status' => $completion?->status,
                    'completion_id' => $completion?->id,
                ];
            }
        }

        return $out;
    }

    /**
     * Hali isboti yo'q (yoki rad etilib tuzatilmagan) ishlar.
     *
     * @return list<array{context: ProofContext, challenge: Challenge, day: string, status: ?CompletionStatus, completion_id: ?int}>
     */
    public function unsent(User $user, string $day): array
    {
        return array_values(array_filter(
            $this->tasks($user, $day),
            fn (array $t) => in_array($t['status'], [null, CompletionStatus::Missed, CompletionStatus::Rejected], true),
        ));
    }

    /**
     * O'tgan kunlar (bugundan tashqari) bo'yicha hali yuborsa bo'ladigan ishlar.
     *
     * @return list<array{context: ProofContext, challenge: Challenge, day: string, status: ?CompletionStatus, completion_id: ?int}>
     */
    public function late(User $user): array
    {
        $today = Clock::todayLocal();
        $out = [];

        for ($day = LateWindow::earliestOpenDay(); $day->lessThan($today); $day = $day->addDay()) {
            array_push($out, ...$this->unsent($user, $day->toDateString()));
        }

        return $out;
    }

    /**
     * API uchun ko'rinish.
     *
     * @param  list<array{context: ProofContext, challenge: Challenge, day: string, status: ?CompletionStatus, completion_id: ?int}>  $tasks
     * @return list<array<string, mixed>>
     */
    public function present(array $tasks): array
    {
        return array_map(fn (array $t) => [
            'context' => [
                'key' => $t['context']->contextKey(),
                'id' => $t['context']->contextId(),
                'title' => $t['context']->contextTitle(),
            ],
            'challenge' => $t['challenge'],
            'day' => $t['day'],
            'status' => $t['status']?->value,
            'completion_id' => $t['completion_id'],
        ], $tasks);
    }
}
