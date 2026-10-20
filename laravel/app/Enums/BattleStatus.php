<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Battle holati — SPEC §3, §6, §17.
 */
enum BattleStatus: string
{
    case Pending = 'pending';       // taklif yuborilgan, qabul kutilyapti
    case Negotiating = 'negotiating'; // qabul qilindi, yutuq/jazo kelishilyapti (§17)
    case Active = 'active';
    case Finished = 'finished';
    case Cancelled = 'cancelled';   // o'zaro bekor
    case Forfeit = 'forfeit';       // bir tomon tashlab ketdi

    /** Chat va tekshiruv uchun ochiqmi. Isbot yuborish — Battle::acceptsProofs(). */
    public function isOpen(): bool
    {
        return $this === self::Active || $this === self::Pending || $this === self::Negotiating;
    }
}
