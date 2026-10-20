<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ReminderService;
use Illuminate\Console\Command;

class RemindCommand extends Command
{
    protected $signature = 'battle:remind';

    protected $description = 'Eslatmalar: odat vaqti, kechki va ertalabki (SPEC §18)';

    public function handle(ReminderService $reminders): int
    {
        $sent = $reminders->run();

        $this->info("Eslatmalar: odat {$sent['habit']}, kechki {$sent['evening']}, kechagi {$sent['late']}.");

        return self::SUCCESS;
    }
}
