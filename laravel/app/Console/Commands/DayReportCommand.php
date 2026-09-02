<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\BattleParticipant;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Quest;
use App\Models\User;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

/**
 * Bir kunning to'liq manzarasi — FAQAT O'QIYDI, hech narsa o'zgartirmaydi.
 *
 * "O'sha kuni nima yubordim?" degan savolga taxmin bilan emas, bazadan javob
 * beradi: shu kuni qaysi odatlar kutilgan, qaysi biri kelgan, soat nechada
 * kelgan va qaysi kunga yozilgan.
 */
class DayReportCommand extends Command
{
    protected $signature = 'battle:day-report
        {--telegram-id= : Kimning kuni (busiz — hamma)}
        {--date= : Qaysi kun (YYYY-MM-DD, default: kecha)}';

    protected $description = 'Bir kunda kutilgan va kelgan isbotlarni ko`rsatadi (faqat o`qiydi)';

    public function handle(): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'), (string) config('telegram.timezone'))->startOfDay()
            : Clock::todayLocal()->subDay();

        $users = $this->users();

        if ($users->isEmpty()) {
            $this->error('Foydalanuvchi topilmadi.');

            return self::FAILURE;
        }

        foreach ($users as $user) {
            $this->reportFor($user, $date);
        }

        $this->newLine();
        $this->line('  <fg=gray>Bu hisobot faqat o\'qidi — hech narsa o\'zgarmadi.</>');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * @return Collection<int, User>
     */
    private function users()
    {
        $tg = $this->option('telegram-id');

        return $tg
            ? User::where('telegram_id', (int) $tg)->get()
            : User::query()->get();
    }

    private function reportFor(User $user, CarbonImmutable $date): void
    {
        $challenges = $this->challengesFor($user);

        if ($challenges->isEmpty()) {
            return;
        }

        $rows = [];

        foreach ($challenges as $challenge) {
            $due = $challenge->start_date->toDateString() <= $date->toDateString()
                && $challenge->cadence->isDue($date, $challenge->weekdaysList());

            $completion = Completion::where('challenge_id', $challenge->id)
                ->where('user_id', $user->id)
                ->whereDate('day', $date->toDateString())
                ->first();

            if (! $due && $completion === null) {
                continue;   // shu kuni navbatda ham emas, yozuv ham yo'q
            }

            $rows[] = [
                $challenge->id,
                $this->containerLabel($challenge),
                mb_strimwidth($challenge->name ?: ($challenge->template_key ?? '—'), 0, 20, '…'),
                $due ? 'ha' : '—',
                $completion?->status->value ?? '✗ YUBORILMAGAN',
                $this->submittedAt($completion),
            ];
        }

        // O'sha kunga yozilmagan, lekin O'SHA KECHA yuborilgan yozuvlar ham
        // muhim — ular boshqa kunga tushib qolgan bo'lishi mumkin.
        $strays = $this->straysNear($user, $date);

        $this->newLine();
        $this->line("  <options=bold>{$user->first_name}</> · <fg=cyan>{$date->toDateString()}</>");

        if ($rows === []) {
            $this->line('  <fg=gray>Bu kuni hech narsa kutilmagan.</>');
        } else {
            $this->table(
                ['Ch', 'Qayerda', 'Odat', 'Navbatda', 'Holat', 'Yuborilgan (Toshkent)'],
                $rows,
            );
        }

        if ($strays->isNotEmpty()) {
            $this->line('  <fg=yellow>⚠ Shu kecha yuborilgan, lekin BOSHQA kunga yozilgan:</>');
            $this->table(
                ['ID', 'Odat', 'Yozilgan kun', 'Yuborilgan (Toshkent)', 'Holat'],
                $strays->map(fn (Completion $c) => [
                    $c->id,
                    mb_strimwidth($c->challenge?->name ?: '—', 0, 20, '…'),
                    $c->day->toDateString(),
                    $this->submittedAt($c),
                    $c->status->value,
                ])->all(),
            );
        }
    }

    /**
     * Foydalanuvchi bajarishi kerak bo'lgan challenge'lar (duel + missiya).
     *
     * @return Collection<int, Challenge>
     */
    private function challengesFor(User $user)
    {
        $battleIds = BattleParticipant::where('user_id', $user->id)->pluck('battle_id');
        // Missiyada faqat EGA bajaradi — guvohlik qilganlari kirmaydi
        $questIds = Quest::where('owner_id', $user->id)->pluck('id');

        return Challenge::query()
            ->where('pending', false)
            ->where(fn ($q) => $q
                ->whereIn('battle_id', $battleIds)
                ->orWhereIn('quest_id', $questIds))
            ->get();
    }

    /**
     * Shu kunning kechasida (00:00–04:00 va oldingi kechqurun) yuborilgan,
     * lekin boshqa kunga yozilgan yozuvlar.
     *
     * @return Collection<int, Completion>
     */
    private function straysNear(User $user, CarbonImmutable $date)
    {
        $tz = (string) config('telegram.timezone');
        $from = $date->setTime(18, 0);                 // o'sha kuni kechqurun
        $to = $date->addDay()->setTime(Clock::dayStartHour(), 0);

        return Completion::query()
            ->with('challenge')
            ->where('user_id', $user->id)
            ->whereNotNull('submitted_at')
            ->whereDate('day', '!=', $date->toDateString())
            ->get()
            ->filter(function (Completion $c) use ($from, $to, $tz) {
                $at = CarbonImmutable::parse($c->submitted_at)->setTimezone($tz);

                return $at->betweenIncluded($from, $to);
            })
            ->values();
    }

    private function containerLabel(Challenge $challenge): string
    {
        return $challenge->quest_id !== null ? '🎯 missiya' : '⚔️ duel';
    }

    private function submittedAt(?Completion $completion): string
    {
        if ($completion?->submitted_at === null) {
            return '—';
        }

        return CarbonImmutable::parse($completion->submitted_at)
            ->setTimezone((string) config('telegram.timezone'))
            ->format('Y-m-d H:i');
    }
}
