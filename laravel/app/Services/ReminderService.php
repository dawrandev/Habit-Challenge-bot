<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ProofContext;
use App\Enums\CompletionStatus;
use App\Models\Battle;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\User;
use App\Services\Telegram\NotificationService;
use App\Support\Clock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Eslatmalar — SPEC §18. Har daqiqa `battle:remind` orqali yuguradi.
 *
 *   habit   — odatga vaqt qo'yilgan bo'lsa, o'sha vaqtda (bugun hali yuborilmagan bo'lsa)
 *   evening — kechqurun (23:00): bugungi yuborilmaganlar bitta xabarda
 *   late    — ertalab (10:00): kecha yuborilmaganlar — hali kech emas
 *
 * Har eslatma `reminder_logs` ga yoziladi: bir xil eslatma ikki marta ketmaydi.
 * Vaqt "oyna" bilan tekshiriladi — cron bir necha daqiqa ishlamay qolsa ham
 * eslatma kechikib bo'lsa-da yetib boradi (lekin oynadan keyin — yo'q).
 */
class ReminderService
{
    private const BUTTON = '📸 Isbot yuborish';

    public function __construct(
        private readonly DueTaskService $tasks,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * @return array{habit: int, evening: int, late: int}
     */
    public function run(?CarbonImmutable $now = null): array
    {
        $now ??= Clock::nowLocal();

        return [
            'habit' => $this->habit($now),
            'evening' => $this->evening($now),
            'late' => $this->late($now),
        ];
    }

    /** Odat vaqtidagi eslatma. */
    public function habit(CarbonImmutable $now): int
    {
        $today = Clock::todayLocal()->toDateString();
        $todayDate = CarbonImmutable::parse($today);
        $sent = 0;

        $challenges = Challenge::query()
            ->whereNotNull('remind_at')
            ->where('active', true)
            ->where('pending', false)
            ->whereDate('start_date', '<=', $today)
            ->with(['battle.participants.user', 'quest.owner'])
            ->get()
            ->filter(fn (Challenge $c) => $this->isDue($c->remind_at, $now)
                && $c->cadence->isDue($todayDate, $c->weekdaysList()));

        foreach ($challenges as $challenge) {
            $context = $challenge->context();
            if (! $context->isOpen() || ! $context->coversDay($today)) {
                continue;
            }

            foreach ($this->submitters($context) as $user) {
                if (! $context->canSubmit($user->id) || $this->hasProof($challenge, $user, $today)) {
                    continue;
                }
                if (! $this->claim($user, 'habit', (string) $challenge->id, $today)) {
                    continue;
                }

                $this->notifications->notifyWithApp(
                    (int) $user->telegram_id,
                    "⏰ <b>{$challenge->remind_at}</b> — {$challenge->icon} <b>{$challenge->label()}</b> vaqti!\n"
                        ."<i>{$context->contextTitle()}</i>\n\n"
                        .'Bajarib bo\'lgach isbotini yubor 📸',
                    self::BUTTON,
                    $this->path($context),
                );
                $sent++;
            }
        }

        return $sent;
    }

    /** Kechki eslatma — bugungi yuborilmaganlar. */
    public function evening(CarbonImmutable $now): int
    {
        if (! $this->isDue((string) config('telegram.reminders.evening_at'), $now)) {
            return 0;
        }

        $today = Clock::todayLocal()->toDateString();
        $closesAt = sprintf('%02d:00', Clock::dayStartHour());

        return $this->digest('evening', $today, fn (User $u) => $this->tasks->unsent($u, $today), fn (string $list) => "🌙 Kun yakunlanmoqda — bugungi hisobotlarni yubor:\n\n{$list}\n\n"
            ."Kun {$closesAt} da yopiladi. Ulgurmasang — ertaga kechagi kun uchun ham yuborsa bo'ladi.");
    }

    /** Ertalabki eslatma — kecha yuborilmaganlar. */
    public function late(CarbonImmutable $now): int
    {
        if (! $this->isDue((string) config('telegram.reminders.late_at'), $now)) {
            return 0;
        }

        $today = Clock::todayLocal()->toDateString();

        return $this->digest('late', $today, fn (User $u) => $this->tasks->late($u), fn (string $list) => "🕊 Kecha bu hisobotlar yuborilmay qoldi:\n\n{$list}\n\n"
            ."Bajargan bo'lsang — bugun kun oxirigacha yuborishing mumkin, ochko qaytadi.");
    }

    // --- ichki --------------------------------------------------------------

    /**
     * Har foydalanuvchiga bitta xabar — ro'yxat bilan. Bo'sh bo'lsa — yubormaydi.
     *
     * @param  callable(User): list<array{context: ProofContext, challenge: Challenge}>  $collect
     * @param  callable(string): string  $compose
     */
    private function digest(string $kind, string $day, callable $collect, callable $compose): int
    {
        $sent = 0;

        foreach ($this->tasks->activeMembers() as $user) {
            $items = $collect($user);
            if ($items === [] || ! $this->claim($user, $kind, '-', $day)) {
                continue;
            }

            $lines = array_map(
                fn (array $t) => "{$t['challenge']->icon} {$t['challenge']->label()} — <i>{$t['context']->contextTitle()}</i>",
                array_slice($items, 0, 12),
            );
            if (count($items) > 12) {
                $lines[] = '… va yana '.(count($items) - 12).' ta';
            }

            // Hammasi bitta duel/missiyada bo'lsa — to'g'ri o'shani ochamiz
            $contexts = array_unique(array_map(fn (array $t) => $this->path($t['context']), $items));

            $this->notifications->notifyWithApp(
                (int) $user->telegram_id,
                $compose(implode("\n", $lines)),
                self::BUTTON,
                count($contexts) === 1 ? $contexts[0] : '/',
            );
            $sent++;
        }

        return $sent;
    }

    /**
     * Ilova kuni ichidagi daqiqa: kun `day_start_hour` da boshlanadi, shuning
     * uchun 23:00 dan keyingi 00:30 "kechagi kunning davomi" — 24:30.
     */
    private function minuteOfAppDay(int $hour, int $minute): int
    {
        $value = $hour * 60 + $minute;

        return $hour < Clock::dayStartHour() ? $value + 1440 : $value;
    }

    private function isDue(?string $time, CarbonImmutable $now): bool
    {
        if ($time === null || ! preg_match('/^(\d{2}):(\d{2})$/', $time, $m)) {
            return false;
        }

        $target = $this->minuteOfAppDay((int) $m[1], (int) $m[2]);
        $current = $this->minuteOfAppDay($now->hour, $now->minute);
        $window = max(1, (int) config('telegram.reminders.window_minutes', 120));

        return $current >= $target && $current < $target + $window;
    }

    /**
     * Eslatmani "band qiladi". Unique kalit tufayli parallel yugurishda ham
     * faqat bittasi true oladi.
     */
    private function claim(User $user, string $kind, string $ref, string $day): bool
    {
        return DB::table('reminder_logs')->insertOrIgnore([
            'user_id' => $user->id,
            'kind' => $kind,
            'ref' => $ref,
            'day' => $day,
            'created_at' => now(),
        ]) === 1;
    }

    private function hasProof(Challenge $challenge, User $user, string $day): bool
    {
        return Completion::where('challenge_id', $challenge->id)
            ->where('user_id', $user->id)
            ->whereDate('day', $day)
            ->where('status', '!=', CompletionStatus::Missed->value)
            ->exists();
    }

    /**
     * @return iterable<User>
     */
    private function submitters(ProofContext $context): iterable
    {
        $users = $context instanceof Battle
            ? $context->participants->pluck('user')
            : collect([$context->owner]);

        return $users->filter(fn (?User $u) => $u !== null && $u->telegram_id);
    }

    private function path(ProofContext $context): string
    {
        return '/'.$context->contextKey().'/'.$context->contextId();
    }
}
