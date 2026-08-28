<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Quest\QuestReport;
use App\Domain\Quest\QuestStatsEngine;
use App\Domain\Quest\TrackedChallenge;
use App\Enums\CompletionStatus;
use App\Enums\QuestStatus;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Quest;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Eloquent va sof QuestStatsEngine orasidagi ko'prik.
 *
 * Tasdiqlangan kunlar BITTA so'rov bilan olinadi (challenge boshiga bittadan emas) —
 * chart sahifasi 8 ta challenge uchun ham 2 ta so'rovda yig'iladi.
 */
class QuestStatsService
{
    public function __construct(private readonly QuestStatsEngine $engine) {}

    public function report(Quest $quest): QuestReport
    {
        $tz = (string) config('telegram.timezone');
        $today = Clock::todayLocal();

        $questStart = CarbonImmutable::parse($quest->start_date->toDateString(), $tz);
        $questEnd = CarbonImmutable::parse($quest->end_date->toDateString(), $tz);

        $quest->loadMissing('challenges');

        /** @var Collection<int, Challenge> $challenges */
        $challenges = $quest->challenges
            ->where('active', true)
            ->where('pending', false)
            ->values();

        $approvedByChallenge = $this->approvedDays(
            $challenges->pluck('id')->all(),
            $quest->owner_id,
        );

        $tracked = $challenges->map(function (Challenge $challenge) use ($approvedByChallenge, $questStart, $tz) {
            // Challenge missiya boshlanishidan oldin boshlana olmaydi — aks holda
            // missiyagacha bo'lgan kunlar "o'tkazib yuborilgan" bo'lib ketardi.
            $challengeStart = CarbonImmutable::parse($challenge->start_date->toDateString(), $tz);

            return new TrackedChallenge(
                id: $challenge->id,
                cadence: $challenge->cadence,
                weekdays: $challenge->weekdaysList(),
                startDate: $challengeStart->greaterThan($questStart) ? $challengeStart : $questStart,
                approvedDays: $approvedByChallenge[$challenge->id] ?? [],
            );
        })->all();

        return $this->engine->build(
            challenges: $tracked,
            start: $questStart,
            end: $questEnd,
            today: $today,
            goalPercent: $quest->goal_percent,
            finished: $quest->status === QuestStatus::Finished,
        );
    }

    /**
     * @param  array<int>  $challengeIds
     * @return array<int, array<string>> challenge_id => ['Y-m-d', ...]
     */
    private function approvedDays(array $challengeIds, int $userId): array
    {
        if ($challengeIds === []) {
            return [];
        }

        return Completion::query()
            ->whereIn('challenge_id', $challengeIds)
            ->where('user_id', $userId)
            ->whereIn('status', [
                CompletionStatus::Approved->value,
                CompletionStatus::AutoApproved->value,
            ])
            ->get(['challenge_id', 'day'])
            ->groupBy('challenge_id')
            ->map(fn ($rows) => $rows
                ->map(fn (Completion $c) => CarbonImmutable::parse($c->day)->format('Y-m-d'))
                ->unique()
                ->values()
                ->all())
            ->all();
    }
}
