<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Scoring\WinnerResolver;
use App\Enums\BattleStatus;
use App\Enums\CompletionStatus;
use App\Enums\StakeKind;
use App\Models\Battle;
use App\Models\BattleParticipant;
use App\Models\Completion;
use App\Services\Telegram\NotificationService;
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
        private readonly NotificationService $notifications,
        private readonly BattleStakeService $stakes,
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

        $battles = Battle::with(['challenges', 'participants.user'])
            ->where('status', BattleStatus::Active->value)
            ->whereDate('end_date', '<', $today->toDateString())
            ->get();

        foreach ($battles as $battle) {
            $this->close($battle);
            $count++;
        }

        return $count;
    }

    /**
     * Bitta duelni davri tugagan bo'lsa joyida yopadi.
     *
     * Cron kechikkan yoki umuman sozlanmagan bo'lsa ham o'yinchi natijani
     * darhol ko'radi — g'olib ekrani cron'ga bog'liq bo'lib qolmasin.
     */
    public function closeIfDue(Battle $battle): bool
    {
        if ($battle->status !== BattleStatus::Active) {
            return false;
        }

        if ($battle->end_date->toDateString() >= Clock::todayLocal()->toDateString()) {
            return false;
        }

        $battle->loadMissing(['challenges', 'participants.user']);
        $this->close($battle);

        return true;
    }

    /**
     * G'olibni aniqlaydi va duelni yopadi.
     *
     * Ball ATAYLAB shu yerda qayta hisoblanadi: `battle_participants.score`
     * faqat `recomputeScores()` da yangilanadi, shuning uchun unga tayanish
     * bu metodni chaqirilish tartibiga bog'liq qilib qo'yardi.
     */
    private function close(Battle $battle): void
    {
        if ($battle->participants->count() === 2) {
            [$a, $b] = [$battle->participants[0], $battle->participants[1]];

            $scoreA = $this->scoring->forParticipant($battle, $a->user_id)['score'];
            $scoreB = $this->scoring->forParticipant($battle, $b->user_id)['score'];

            $result = $this->winnerResolver->decide(
                $scoreA,
                $this->scoring->creditedCount($battle, $a->user_id),
                $scoreB,
                $this->scoring->creditedCount($battle, $b->user_id),
            );

            $battle->winner_id = match (true) {
                $result > 0 => $a->user_id,
                $result < 0 => $b->user_id,
                default => null,
            };

            // Yakuniy ballni muhrlaymiz — arxivda o'zgarmas bo'lib qolsin
            $a->update(['score' => $scoreA]);
            $b->update(['score' => $scoreB]);
        }

        $this->sealHabits($battle);

        $battle->status = BattleStatus::Finished;
        $battle->save();

        $this->announce($battle);
    }

    /**
     * Odat progressi va jazo holatini muhrlaydi (SPEC §17).
     *
     * Jazo faqat garovli duelda — garovsiz duelda kelishilgan jazo yo'q,
     * shuning uchun `penalized` null qoladi.
     */
    private function sealHabits(Battle $battle): void
    {
        $rule = $this->stakes->rule($battle);
        $withStakes = $battle->hasStakes();

        foreach ($battle->participants as $participant) {
            ['done' => $done, 'expected' => $expected] = $this->scoring->habitProgress($battle, $participant->user_id);

            $participant->update([
                'habits_done' => $done,
                'habits_expected' => $expected,
                'penalized' => $withStakes ? $rule->isPenalized($done, $expected) : null,
            ]);
        }
    }

    /**
     * Natijani ikkala o'yinchiga e'lon qiladi.
     */
    private function announce(Battle $battle): void
    {
        $battle->loadMissing('participants.user');
        $prize = $this->stakes->chosenText($battle, StakeKind::Prize);
        $penalty = $this->stakes->chosenText($battle, StakeKind::Penalty);

        $winner = $battle->winner_id !== null
            ? $battle->participants->firstWhere('user_id', $battle->winner_id)?->user
            : null;

        foreach ($battle->participants as $participant) {
            $user = $participant->user;
            if ($user === null || ! $user->telegram_id) {
                continue;
            }

            if ($winner === null) {
                $text = "🤝 <b>{$battle->title}</b> — durang!\nHech kim yutmadi.";
            } elseif ($winner->id === $user->id) {
                $text = "🏆 <b>{$battle->title}</b> — SEN YUTDING!\nTabriklaymiz 🔥";
                if ($prize !== null) {
                    $text .= "\n\n🎁 Yutug'ing: <b>{$prize}</b>";
                }
            } else {
                $text = "🏁 <b>{$battle->title}</b> yakunlandi.\nG'olib: <b>{$winner->first_name}</b>";
                if ($prize !== null) {
                    $text .= "\n\n🎁 Kelishilgan yutuq: <b>{$prize}</b> — va'da — qarz 😉";
                }
            }

            $text .= $this->penaltyLine($battle, $participant, $penalty);

            $this->notifications->notify([$user->telegram_id], $text);
        }
    }

    private function penaltyLine(Battle $battle, BattleParticipant $participant, ?string $penalty): string
    {
        if ($penalty === null || $participant->penalized === null) {
            return '';
        }

        $stat = "{$participant->habits_done}/{$participant->habits_expected}";

        return $participant->penalized
            ? "\n\n⚡ Sen odatlarning {$stat} tasini bajarding ({$battle->penalty_threshold}% yoki kam).\n"
                ."Kelishilgan jazo: <b>{$penalty}</b>"
            : "\n\n🛡 Jazodan qutulding: {$stat} bajarildi.";
    }

    public function runDailyClose(): void
    {
        $this->markMissed();
        $this->recomputeScores();
        $this->finishDue();
    }
}
