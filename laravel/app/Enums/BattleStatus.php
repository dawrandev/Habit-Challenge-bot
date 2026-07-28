<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Battle holati — SPEC §3, §6.
 */
enum BattleStatus: string
{
    case Pending = 'pending';       // taklif yuborilgan, qabul kutilyapti
    case Active = 'active';
    case Finished = 'finished';
    case Cancelled = 'cancelled';   // o'zaro bekor
    case Forfeit = 'forfeit';       // bir tomon tashlab ketdi

    public function isOpen(): bool
    {
        return $this === self::Active || $this === self::Pending;
    }
}
