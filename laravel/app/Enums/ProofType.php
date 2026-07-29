<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Challenge isbot turi — SPEC §9 kengaytmasi.
 */
enum ProofType: string
{
    case Camera = 'camera';         // jonli kamera (default, anti-cheat)
    case Screenshot = 'screenshot'; // skrinshot (galereya — ekran vaqti, qadamlar)
}
