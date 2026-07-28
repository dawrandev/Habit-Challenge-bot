<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Nizo holati — SPEC §5, §12–13.
 */
enum DisputeStatus: string
{
    case Open = 'open';
    case ResolvedApproved = 'resolved_approved';  // tekshiruvchi fikrini o'zgartirdi
    case ResolvedUpheld = 'resolved_upheld';      // "bahsli rad" — qaror kuchida
}
