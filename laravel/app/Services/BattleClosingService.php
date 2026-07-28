<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Scoring\WinnerResolver;
use App\Enums\BattleStatus;
use App\Enums\CompletionStatus;
use App\Models\Battle;
use App\Models\Completion;
use App\Support\Clock;
use Carbon\CarbonImmutable;

/**
 * Kunlik yopish + auto-tasdiq + g'olibni aniqlash — SPEC §4, §5, §11.
 */
class BattleClosingService
{
    public function __construct(
        private readonly ScoringService $scoring,
        private readonly WinnerResolver $winnerResolver,
    ) {}

    /**
     * 24s tekshirilmagan hisobotlarni avtomatik tasdiqlaydi — SPEC §11.
     */
    public function autoApproveOverdue(): int
    {
        $cutoff = now()->subHours((int) config('telegram.verify_deadline_hours'));

        $pending = Completion::where('status', CompletionStatus::Pending->value)
            ->where('submitted_at', '<', $cutoff)
            ->get();

        foreach ($pending as $completion) {
            $completion->update([
                'status' => CompletionStatus::AutoApproved,
                'resolved_at' => now(),
            ]);
        }

        return $pending->count();
    }

    /**
     * Kechagi va oldingi navbatdagi kunlarda hisobot bo'lmasa → missed.
     */
    public function markMissed(): int
    {
        $today = Clock::todayLocal();
        $count = 0;

        foreach (Battle::with(['challenges', 'participants'])->where('status', BattleStatus::Active->value)->get() as $battle) {
            $lastDay = CarbonImmutable::parse($battle->end_date->toDateString())->min($today->subDay());

            foreach ($battle->challenges as $challenge) {
                $day = CarbonImmutable::parse($challenge->start_date->toDateString());

                while ($day->lessThanOrEqualTo($lastDay)) {
                    if ($challenge->cadence->isDue($day, $challenge->weekdaysList())) {
                        foreach ($battle->participants as $participant) {
                            $exists = Completion::where('challenge_id', $challenge->id)
                                ->where('user_id', $participant->user_id)
                                ->whereDate('day', $day->toDateString())
                                ->exists();

                            if (! $exists) {
                                Completion::create([
                                    'challenge_id' => $challenge->id,
                                    'user_id' => $participant->user_id,
                                    'day' => $day->toDateString(),
                                    'status' => CompletionStatus::Missed,
                                ]);
                                $count++;
                            }
                        }
                    }
                    $day = $day->addDay();
                }
            }
        }

        return $count;
    }

    /**
     * Faol battle'lardagi ishtirokchi ballarini qayta hisoblaydi.
     */
    public function recomputeScores(): void
    {
        foreach (Battle::with(['challenges', 'participants'])->where('status', BattleStatus::Active->value)->get() as $battle) {
            foreach ($battle->participants as $participant) {
                $participant->update([
                    'score' => $this->scoring->forParticipant($battle, $participant->user_id)['score'],
                ]);
            }
        }
    }

    /**
     * Davri tugagan battle'larni yakunlaydi, g'olibni aniqlaydi — SPEC §4.
     */
    public function finishDue(): int
    {
        $today = Clock::todayLocal();
        $count = 0;

        $battles = Battle::with(['challenges', 'participants'])
            ->where('status', BattleStatus::Active->value)
            ->whereDate('end_date', '<', $today->toDateString())
            ->get();

        foreach ($battles as $battle) {
            if ($battle->participants->count() === 2) {
                [$a, $b] = [$battle->participants[0], $battle->participants[1]];

                $result = $this->winnerResolver->decide(
                    $a->score,
                    $this->scoring->creditedCount($battle, $a->user_id),
                    $b->score,
                    $this->scoring->creditedCount($battle, $b->user_id),
                );

                $battle->winner_id = match (true) {
                    $result > 0 => $a->user_id,
                    $result < 0 => $b->user_id,
                    default => null,
                };
            }

            $battle->status = BattleStatus::Finished;
            $battle->save();
            $count++;
        }

        return $count;
    }

    public function runDailyClose(): void
    {
        $this->markMissed();
        $this->recomputeScores();
        $this->finishDue();
    }
}
