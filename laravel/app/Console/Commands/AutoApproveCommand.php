<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\BattleClosingService;
use Illuminate\Console\Command;

class AutoApproveCommand extends Command
{
    protected $signature = 'battle:auto-approve';

    protected $description = '24s o\'tgan tekshirilmagan hisobotlarni avtomatik tasdiqlaydi (SPEC §11)';

    public function handle(BattleClosingService $service): int
    {
        $count = $service->autoApproveOverdue();
        if ($count > 0) {
            $service->recomputeScores();
        }
        $this->info("Auto-tasdiq: {$count} ta hisobot.");

        return self::SUCCESS;
    }
}
