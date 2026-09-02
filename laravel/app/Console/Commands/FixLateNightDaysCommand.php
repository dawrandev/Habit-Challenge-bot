<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\CompletionStatus;
use App\Models\Completion;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Bir martalik tuzatish: yarim tundan keyin yuborilgan eski isbotlarni
 * o'zi tegishli bo'lgan kunga qaytaradi.
 *
 * Kun chegarasi `day_start_hour` ga o'tishidan OLDIN yuborilgan isbotlar
 * qat'iy yarim tun qoidasi bilan yozilgan: 00:05 da yuborilgan isbot
 * keyingi kunga tushib qolgan, holbuki odam uni o'sha kechada bajargan.
 *
 * ATAYLAB dry-run: hech narsa `--apply` siz o'zgarmaydi.
 */
class FixLateNightDaysCommand extends Command
{
    protected $signature = 'battle:fix-late-nights
        {--apply : O`zgarishni haqiqatan qo`llash (busiz faqat ko`rsatadi)}
        {--since= : Faqat shu sanadan keyingilari (YYYY-MM-DD)}
        {--telegram-id= : Faqat bitta foydalanuvchi}';

    protected $description = 'Yarim tundan keyin yuborilgan eski isbotlarni to`g`ri kunga qaytaradi';

    public function handle(): int
    {
        $cutoff = Clock::dayStartHour();

        if ($cutoff === 0) {
            $this->warn('day_start_hour = 0 — kun yarim tunda almashadi, tuzatadigan narsa yo\'q.');

            return self::SUCCESS;
        }

        $candidates = $this->candidates($cutoff);

        if ($candidates->isEmpty()) {
            $this->info('Tuzatishga muhtoj isbot topilmadi.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->line("  <options=bold>Kun chegarasi: {$cutoff}:00 — quyidagilar noto'g'ri kunga yozilgan</>");
        $this->newLine();

        $rows = [];
        $movable = [];
        $blocked = 0;

        foreach ($candidates as $completion) {
            $target = CarbonImmutable::parse($completion->day)->subDay()->toDateString();
            $reason = $this->blocker($completion, $target);

            $rows[] = [
                $completion->id,
                $completion->user?->first_name ?? '?',
                mb_strimwidth($completion->challenge?->name ?: '—', 0, 18, '…'),
                $this->localTime($completion),
                $completion->day->toDateString().'  →  '.$target,
                $reason === null ? '✓' : "✗ {$reason}",
            ];

            if ($reason === null) {
                $movable[$completion->id] = $target;
            } else {
                $blocked++;
            }
        }

        $this->table(
            ['ID', 'Kim', 'Odat', 'Yuborilgan (Toshkent)', 'Kun', 'Holat'],
            $rows,
        );

        $this->line('  Ko\'chiriladi: <fg=green>'.count($movable).'</>  ·  o\'tkazib yuboriladi: <fg=yellow>'.$blocked.'</>');
        $this->newLine();

        if (! $this->option('apply')) {
            $this->warn('  Bu — DRY RUN, hech narsa o\'zgarmadi.');
            $this->line('  Qo\'llash uchun: <options=bold>php artisan battle:fix-late-nights --apply</>');
            $this->newLine();

            return self::SUCCESS;
        }

        if ($movable === []) {
            $this->warn('  Ko\'chiriladigan yozuv yo\'q.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($movable) {
            foreach ($movable as $id => $day) {
                Completion::whereKey($id)->update(['day' => $day]);
            }
        });

        $this->info('  ✓ '.count($movable).' ta isbot to\'g\'ri kunga qaytarildi.');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * Eski qoida bilan yozilganlar: yarim tun bilan chegara orasida
     * yuborilgan VA yuborilgan kalendar kuniga yozilgan isbotlar.
     *
     * @return Collection<int, Completion>
     */
    private function candidates(int $cutoff)
    {
        $query = Completion::query()
            ->with(['user', 'challenge'])
            ->whereNotNull('submitted_at')
            // `missed` yozuvlari yuborilmagan — ularga tegmaymiz
            ->whereIn('status', [
                CompletionStatus::Pending->value,
                CompletionStatus::Approved->value,
                CompletionStatus::AutoApproved->value,
                CompletionStatus::Rejected->value,
            ]);

        if ($since = $this->option('since')) {
            $query->whereDate('day', '>=', $since);
        }

        if ($tg = $this->option('telegram-id')) {
            $query->whereHas('user', fn ($q) => $q->where('telegram_id', (int) $tg));
        }

        return $query->get()->filter(function (Completion $completion) use ($cutoff) {
            $localSubmit = $this->localSubmit($completion);

            // Cho'zilgan kechada yuborilganmi?
            if ($localSubmit->hour >= $cutoff) {
                return false;
            }

            // Yangi qoida bilan yozilgan bo'lsa `day` allaqachon oldingi kun —
            // ularga tegmaymiz (buyruq ikki marta yurishi xavfsiz bo'lsin).
            return $completion->day->toDateString() === $localSubmit->toDateString();
        })->values();
    }

    /**
     * Ko'chirishga to'sqinlik qiladigan sabab (yoki null).
     */
    private function blocker(Completion $completion, string $target): ?string
    {
        $challenge = $completion->challenge;

        if ($challenge === null) {
            return 'challenge yo\'q';
        }

        if ($target < $challenge->start_date->toDateString()) {
            return 'davrdan oldin';
        }

        $taken = Completion::where('challenge_id', $completion->challenge_id)
            ->where('user_id', $completion->user_id)
            ->whereDate('day', $target)
            ->exists();

        return $taken ? 'o\'sha kun band' : null;
    }

    private function localSubmit(Completion $completion): CarbonImmutable
    {
        return CarbonImmutable::parse($completion->submitted_at)
            ->setTimezone((string) config('telegram.timezone'));
    }

    private function localTime(Completion $completion): string
    {
        return $this->localSubmit($completion)->format('Y-m-d H:i');
    }
}
