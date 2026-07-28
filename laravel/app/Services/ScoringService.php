<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Scoring\ChallengeScore;
use App\Domain\Scoring\ScoringEngine;
use App\Enums\CompletionStatus;
use App\Models\Battle;
use App\Models\Completion;
use App\Support\Clock;
use Carbon\CarbonImmutable;

/**
 * Eloquent va sof Scoring domeni orasidagi ko'prik.
 */
class ScoringService
{
    public function __construct(private readonly ScoringEngine $engine) {}

    /**
     * Ishtirokchining umumiy balli + har challenge bo'yicha taqsimot.
     *
     * @return array{score: float, breakdown: array<int, int>}
     */
    public function forParticipant(Battle $battle, int $userId): array
    {
        $tz = (string) config('telegram.timezone');
        $today = Clock::todayLocal();
        $end = CarbonImmutable::parse($battle->end_date->toDateString(), $tz);

        $challengeScores = [];
        $breakdown = [];

        foreach ($battle->challenges as $challenge) {
            $approvedDays = Completion::query()
                ->where('challenge_id', $challenge->id)
                ->where('user_id', $userId)
                ->whereIn('status', [
                    CompletionStatus::Approved->value,
                    CompletionStatus::AutoApproved->value,
                ])
                ->pluck('day')
                ->map(fn ($day) => CarbonImmutable::parse($day)->format('Y-m-d'))
                ->all();

            $score = new ChallengeScore(
                $challenge->cadence,
                $challenge->weekdaysList(),
                CarbonImmutable::parse($challenge->start_date->toDateString(), $tz),
                $approvedDays,
            );

            $challengeScores[] = $score;
            $breakdown[$challenge->id] = $this->engine->challengeCompletions($score, $today, $end);
        }

        return [
            'score' => $this->engine->participantScore($challengeScores, $today, $end),
            'breakdown' => $breakdown,
        ];
    }

    /**
     * Tiebreaker uchun: ishtirokchining battle bo'yicha tasdiqlangan hisobotlari soni.
     */
    public function creditedCount(Battle $battle, int $userId): int
    {
        return Completion::query()
            ->whereIn('challenge_id', $battle->challenges->pluck('id'))
            ->where('user_id', $userId)
            ->whereIn('status', [
                CompletionStatus::Approved->value,
                CompletionStatus::AutoApproved->value,
            ])
            ->count();
    }
}
