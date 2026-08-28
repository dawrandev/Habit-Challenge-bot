<?php

declare(strict_types=1);

namespace App\Domain\Quest;

use App\Enums\QuestOutcome;
use Carbon\CarbonImmutable;

/**
 * Missiya statistikasi — sof biznes-logika, Eloquent'siz.
 *
 * Qoidalar (duel scoring'i bilan bir falsafada, SPEC §4):
 *   - Tasdiqlangan slot          → done
 *   - Kun tugadi, tasdiq yo'q    → missed
 *   - Bugungi hal bo'lmagan slot → pending (jarima EMAS, kun tugamagan)
 *   - Navbat yo'q kun            → dam (streak'ni buzmaydi, uzaytirmaydi ham)
 *
 * Foiz = done / (done + missed). Bugungi tugallanmagan ish foizni pasaytirmaydi.
 */
final class QuestStatsEngine
{
    /**
     * @param  array<TrackedChallenge>  $challenges
     */
    public function build(
        array $challenges,
        CarbonImmutable $start,
        CarbonImmutable $end,
        CarbonImmutable $today,
        int $goalPercent,
        bool $finished = false,
    ): QuestReport {
        $start = $start->startOfDay();
        $end = $end->startOfDay();
        $today = $today->startOfDay();

        $days = [];
        $cells = [];
        $tally = [];

        foreach ($challenges as $challenge) {
            $cells[$challenge->id] = [];
            $tally[$challenge->id] = ['done' => 0, 'missed' => 0, 'pending' => 0];
        }

        $done = $missed = $pending = $planned = 0;

        for ($day = $start; $day->lessThanOrEqualTo($end); $day = $day->addDay()) {
            $isFuture = $day->greaterThan($today);
            $openToday = $day->equalTo($today) && ! $finished;

            $dayDue = 0;
            $dayDone = 0;

            foreach ($challenges as $challenge) {
                if (! $challenge->isDue($day)) {
                    $cells[$challenge->id][] = CellState::Rest->value;

                    continue;
                }

                $dayDue++;
                $planned++;

                if ($isFuture) {
                    $cells[$challenge->id][] = CellState::Future->value;

                    continue;
                }

                if ($challenge->isApproved($day)) {
                    $dayDone++;
                    $done++;
                    $tally[$challenge->id]['done']++;
                    $cells[$challenge->id][] = CellState::Done->value;

                    continue;
                }

                // Bugun hali tugamagan — hal bo'lmagan, jarima yo'q.
                if ($openToday) {
                    $pending++;
                    $tally[$challenge->id]['pending']++;
                    $cells[$challenge->id][] = CellState::Pending->value;

                    continue;
                }

                $missed++;
                $tally[$challenge->id]['missed']++;
                $cells[$challenge->id][] = CellState::Missed->value;
            }

            $days[] = new DayCell(
                date: $day->format('Y-m-d'),
                due: $dayDue,
                done: $dayDone,
                state: $this->dayState($dayDue, $dayDone, $isFuture, $openToday),
            );
        }

        $resolved = $done + $missed;
        $rate = $resolved > 0 ? round($done / $resolved * 100, 1) : 0.0;

        // Bundan buyon hammasi bajarilsa erishiladigan maksimum. O'tkazib
        // yuborilgan slotni qaytarib bo'lmaydi, shuning uchun shift pasayadi.
        $ceilingRate = $planned > 0 ? round(($planned - $missed) / $planned * 100, 1) : 100.0;

        $challengeStats = [];
        foreach ($challenges as $challenge) {
            $row = $tally[$challenge->id];
            $rowResolved = $row['done'] + $row['missed'];
            $challengeStats[] = new ChallengeStat(
                challengeId: $challenge->id,
                done: $row['done'],
                missed: $row['missed'],
                pending: $row['pending'],
                rate: $rowResolved > 0 ? round($row['done'] / $rowResolved * 100, 1) : 0.0,
                currentStreak: $this->challengeCurrentStreak($challenge, $start, $today, $finished),
                longestStreak: $this->challengeLongestStreak($challenge, $start, $today, $end, $finished),
                cells: $cells[$challenge->id],
            );
        }

        $elapsed = $today->lessThan($start)
            ? 0
            : (int) $start->diffInDays($today->min($end)) + 1;

        return new QuestReport(
            done: $done,
            missed: $missed,
            pending: $pending,
            planned: $planned,
            rate: $rate,
            ceilingRate: $ceilingRate,
            currentStreak: $this->currentStreak($days, $today),
            longestStreak: $this->longestStreak($days),
            daysElapsed: $elapsed,
            daysTotal: (int) $start->diffInDays($end) + 1,
            goalPercent: $goalPercent,
            goalReachable: $ceilingRate >= $goalPercent,
            outcome: $finished
                ? ($rate >= $goalPercent ? QuestOutcome::Achieved : QuestOutcome::Missed)
                : null,
            days: $days,
            challenges: $challengeStats,
        );
    }

    private function dayState(int $due, int $done, bool $isFuture, bool $openToday): CellState
    {
        return match (true) {
            $isFuture => CellState::Future,
            $due === 0 => CellState::Rest,
            $done === $due => CellState::Done,
            $openToday => CellState::Pending,
            $done > 0 => CellState::Partial,
            default => CellState::Missed,
        };
    }

    /**
     * Joriy seriya — bugundan orqaga qarab "mukammal kunlar".
     *
     * Dam kunlari seriyani buzmaydi (uzaytirmaydi ham). Bugun hali tugamagan
     * bo'lsa uni o'tkazib yuboramiz — tugamagan kun uchun jazolamaymiz.
     *
     * @param  array<DayCell>  $days
     */
    private function currentStreak(array $days, CarbonImmutable $today): int
    {
        $todayStr = $today->format('Y-m-d');
        $streak = 0;

        foreach (array_reverse($days) as $cell) {
            if ($cell->date > $todayStr) {
                continue;   // kelajak
            }
            if ($cell->isRest()) {
                continue;   // dam kuni — neytral
            }
            if ($cell->isPerfect()) {
                $streak++;

                continue;
            }
            // Bugun hali ochiq — seriyani uzmaydi, lekin qo'shmaydi ham
            if ($cell->date === $todayStr && $cell->state === CellState::Pending) {
                continue;
            }

            break;
        }

        return $streak;
    }

    /**
     * @param  array<DayCell>  $days
     */
    private function longestStreak(array $days): int
    {
        $best = 0;
        $run = 0;

        foreach ($days as $cell) {
            if ($cell->state === CellState::Future || $cell->state === CellState::Pending) {
                continue;
            }
            if ($cell->isRest()) {
                continue;
            }
            if ($cell->isPerfect()) {
                $run++;
                $best = max($best, $run);

                continue;
            }
            $run = 0;
        }

        return $best;
    }

    private function challengeCurrentStreak(
        TrackedChallenge $challenge,
        CarbonImmutable $start,
        CarbonImmutable $today,
        bool $finished,
    ): int {
        $streak = 0;

        for ($day = $today; $day->greaterThanOrEqualTo($start); $day = $day->subDay()) {
            if (! $challenge->isDue($day)) {
                continue;
            }
            if ($challenge->isApproved($day)) {
                $streak++;

                continue;
            }
            if ($day->equalTo($today) && ! $finished) {
                continue;   // bugun hali tugamagan
            }

            break;
        }

        return $streak;
    }

    private function challengeLongestStreak(
        TrackedChallenge $challenge,
        CarbonImmutable $start,
        CarbonImmutable $today,
        CarbonImmutable $end,
        bool $finished,
    ): int {
        $limit = $today->min($end);
        $best = 0;
        $run = 0;

        for ($day = $start; $day->lessThanOrEqualTo($limit); $day = $day->addDay()) {
            if (! $challenge->isDue($day)) {
                continue;
            }
            if ($challenge->isApproved($day)) {
                $run++;
                $best = max($best, $run);

                continue;
            }
            if ($day->equalTo($today) && ! $finished) {
                continue;   // tugamagan kun seriyani uzmaydi
            }
            $run = 0;
        }

        return $best;
    }
}
