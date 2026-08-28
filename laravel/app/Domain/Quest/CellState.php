<?php

declare(strict_types=1);

namespace App\Domain\Quest;

/**
 * Tracker katagi holati.
 *
 * DIQQAT (a11y): bu holatlar UI'da RANG BILAN YOLG'IZ ko'rsatilmaydi.
 * `approve` yashil va `reject` qizil deuteranopiyada ΔE ≈ 6.2 — ajratib
 * bo'lmaydi. Har katak glif ham oladi (✓ / ✕ / ·) — rang ikkilamchi kanal.
 */
enum CellState: string
{
    case Done = 'done';         // ✓ hammasi bajarilgan
    case Partial = 'partial';   // ◐ qisman
    case Missed = 'missed';     // ✕ kun tugadi, bajarilmagan
    case Pending = 'pending';   // ⏳ bugun, hali hal bo'lmagan
    case Rest = 'rest';         // · navbat yo'q (dam kuni)
    case Future = 'future';     // kelajak
}
