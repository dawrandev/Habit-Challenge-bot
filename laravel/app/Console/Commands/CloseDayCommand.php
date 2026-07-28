<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BattleClosingService;
use Illuminate\Console\Command;

class CloseDayCommand extends Command
{
    protected $signature = 'battle:close-day';

    protected $description = 'Kunlik yopish: missed belgilash, ball qayta hisoblash, g\'olibni aniqlash';

    public function handle(BattleClosingService $service): int
    {
        $service->runDailyClose();
        $this->info('Kunlik yopish bajarildi.');

        return self::SUCCESS;
    }
}
