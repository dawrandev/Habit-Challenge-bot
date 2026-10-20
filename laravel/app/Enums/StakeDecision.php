<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Garov qanday tanlandi.
 */
enum StakeDecision: string
{
    case Agreement = 'agreement';   // ikkalasi bir variantni tanladi
    case Coin = 'coin';             // ovozlar har xil — tanga tashlandi
}
