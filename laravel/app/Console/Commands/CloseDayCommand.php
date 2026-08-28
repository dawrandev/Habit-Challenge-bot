<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BattleClosingService;
use App\Services\QuestClosingService;
use Illuminate\Console\Command;

class CloseDayCommand extends Command
{
    protected $signature = 'battle:close-day';

    protected $description = 'Kunlik yopish: missed belgilash, ball qayta hisoblash, g\'olibni aniqlash';

    public function handle(BattleClosingService $battles, QuestClosingService $quests): int
    {
        $battles->runDailyClose();
        $finished = $quests->finishDue();

        $this->info("Kunlik yopish bajarildi. Yakunlangan missiya: {$finished}.");

        return self::SUCCESS;
    }
}
