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
use App\Models\User;
use App\Services\Telegram\NotificationService;
use App\Support\Clock;
use App\Support\LateWindow;
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
        private readonly StakeObligationService $obligations,
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
     *
     * Oxirgi kundan keyin yana kechagi hisobot oynasi (SPEC §18) kutiladi:
     * oxirgi kuni bajarib, yuborishni unutgan ham ulgursin.
     */
    public function finishDue(): int
    {
        $count = 0;

        $battles = Battle::with(['challenges', 'participants.user'])
            ->where('status', BattleStatus::Active->value)
            ->whereDate('end_date', '<', LateWindow::earliestOpenDay()->toDateString())
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

        if (! LateWindow::isPast($battle->end_date->toDateString())) {
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
        Completion::approvePendingOnClose($battle->challenges->pluck('id')->all());

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
     * Natijani ikkala o'yinchiga e'lon qiladi — har kimga o'z nuqtai nazaridan:
     * kim yutdi, ENDI SEN NIMA QILASAN, senga nima qilinadi (SPEC §17).
     * Tugma natija sahifasini ochadi — va'da isbotini o'sha yerda yuklaydi.
     */
    private function announce(Battle $battle): void
    {
        $battle->loadMissing('participants.user');
        $title = e($battle->title);
        $obligations = $this->obligations->obligations($battle);

        $winner = $battle->winner_id !== null
            ? $battle->participants->firstWhere('user_id', $battle->winner_id)?->user
            : null;

        foreach ($battle->participants as $participant) {
            $user = $participant->user;
            if ($user === null || ! $user->telegram_id) {
                continue;
            }

            $text = match (true) {
                $winner === null => "🤝 <b>{$title}</b> — DURANG!\nIkkalangiz teng kuchli chiqdingiz 💪",
                $winner->id === $user->id => "🏆 <b>{$title}</b> — SEN YUTDING!\nTabriklaymiz 🔥",
                default => "🏁 <b>{$title}</b> yakunlandi.\nG'olib: <b>".e($winner->first_name).'</b>',
            };

            $mine = array_filter($obligations, fn (array $o) => $o['debtor']->id === $user->id);
            $toMe = array_filter($obligations, fn (array $o) => $o['creditor']?->id === $user->id);
            $text .= $this->owedLines($battle, $participant, $mine, $toMe);

            [$button, $hint] = match (true) {
                $mine !== [] => ['📸 Isbot yuklash', "\n\n📸 Bajarganingda rasm yoki video yukla — tarixda qoladi, keyin birga eslaysizlar."],
                $toMe !== [] => ['🏁 Natijani ko\'rish', "\n\nU bajarganda isbotini shu yerda ko'rasan 👀"],
                default => ['🏁 Natijani ko\'rish', ''],
            };

            $this->notifications->notifyWithApp((int) $user->telegram_id, $text.$hint, $button, "/battle/{$battle->id}");
        }
    }

    /**
     * "Endi nima bo'ladi" bo'limi: o'zing bajaradiganlar, senga bajariladiganlar
     * va jazo holati.
     *
     * @param  array<array{kind: StakeKind, debtor: User, creditor: ?User, text: string}>  $mine
     * @param  array<array{kind: StakeKind, debtor: User, creditor: ?User, text: string}>  $toMe
     */
    private function owedLines(Battle $battle, BattleParticipant $me, array $mine, array $toMe): string
    {
        $lines = [];

        foreach ($toMe as $o) {
            $lines[] = '🎁 '.e($o['debtor']->first_name).' senga: <b>'.e($o['text']).'</b>';
        }

        foreach ($mine as $o) {
            $lines[] = $o['kind'] === StakeKind::Prize
                ? '🤝 Sen '.e($o['creditor']?->first_name ?? '?').' uchun: <b>'.e($o['text']).'</b>'
                : '⚡ Jazo — sen bajarasan: <b>'.e($o['text']).'</b>'
                    ."\n     (odatlarning {$me->habits_done}/{$me->habits_expected} tasi — {$battle->penalty_threshold}% yoki kam)";
        }

        // Jazodan qutulganini ham aytamiz — bu ham natija
        if ($me->penalized === false && $this->stakes->chosenText($battle, StakeKind::Penalty) !== null) {
            $lines[] = "🛡 Jazodan qutulding: {$me->habits_done}/{$me->habits_expected} bajarildi";
        }

        return $lines === [] ? '' : "\n\n<b>Endi nima bo'ladi:</b>\n".implode("\n", $lines);
    }

    public function runDailyClose(): void
    {
        $this->markMissed();
        $this->recomputeScores();
        $this->finishDue();
    }
}
