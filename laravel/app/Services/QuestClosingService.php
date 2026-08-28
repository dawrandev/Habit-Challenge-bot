<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\QuestOutcome;
use App\Enums\QuestStatus;
use App\Models\Quest;
use App\Services\Telegram\NotificationService;
use App\Support\Clock;

/**
 * Davri tugagan missiyalarni yakunlaydi va natijani e'lon qiladi.
 *
 * Duelning `finishDue()`sidan farqi: g'olib yo'q — maqsad foiziga yetildimi,
 * yetilmadimi. Yutqazuvchi ham yo'q, chunki raqib yo'q.
 */
class QuestClosingService
{
    public function __construct(
        private readonly QuestStatsService $stats,
        private readonly NotificationService $notifications,
    ) {}

    public function finishDue(): int
    {
        $today = Clock::todayLocal();

        $quests = Quest::with(['challenges', 'owner', 'witness'])
            ->where('status', QuestStatus::Active->value)
            ->whereDate('end_date', '<', $today->toDateString())
            ->get();

        foreach ($quests as $quest) {
            // Avval yopamiz — shundan keyin statistika oxirgi kunni ham
            // "hal bo'lgan" deb sanaydi (ochiq bugun qolmaydi).
            $quest->status = QuestStatus::Finished;
            $quest->save();

            $report = $this->stats->report($quest);

            $quest->outcome = $report->outcome ?? QuestOutcome::Missed;
            $quest->save();

            $this->announce($quest, $report->rate);
        }

        return $quests->count();
    }

    private function announce(Quest $quest, float $rate): void
    {
        $achieved = $quest->outcome === QuestOutcome::Achieved;
        $headline = $achieved ? '🏆 Maqsadga yetding!' : '🎯 Missiya yakunlandi';
        $percent = rtrim(rtrim(number_format($rate, 1, '.', ''), '0'), '.');

        $text = "{$headline}\n<b>{$quest->title}</b>\n"
            ."Bajarish: <b>{$percent}%</b> (maqsad {$quest->goal_percent}%)";

        if ($quest->owner !== null && $quest->owner->telegram_id) {
            $this->notifications->notify([$quest->owner->telegram_id], $text);
        }

        if ($quest->witness !== null && $quest->witness->telegram_id) {
            $name = $quest->owner?->first_name ?? '—';
            $this->notifications->notify(
                [$quest->witness->telegram_id],
                "{$headline}\n<b>{$name}</b> — «{$quest->title}»\n"
                    ."Bajarish: <b>{$percent}%</b> (maqsad {$quest->goal_percent}%)\n"
                    .'Guvohliging uchun rahmat 🙏',
            );
        }
    }
}
