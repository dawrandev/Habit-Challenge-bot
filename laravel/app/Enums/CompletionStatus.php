<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Kunlik hisobot holati — SPEC §4, §5.
 */
enum CompletionStatus: string
{
    case Pending = 'pending';               // yuborilgan, tekshiruv kutilyapti
    case Approved = 'approved';             // inson tasdiqladi
    case AutoApproved = 'auto_approved';    // 24s deadline o'tdi
    case Rejected = 'rejected';             // rad etilgan
    case Missed = 'missed';                 // umuman yuborilmagan (kun yopilganda)

    /**
     * Ball beradigan (hisobga kiradigan) holatmi? — SPEC §4.
     */
    public function isCredited(): bool
    {
        return $this === self::Approved || $this === self::AutoApproved;
    }

    public function isResolved(): bool
    {
        return $this !== self::Pending;
    }
}
