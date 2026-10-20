<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Garov turi — duel boshlanishidan oldin kelishiladi (SPEC §17).
 */
enum StakeKind: string
{
    case Prize = 'prize';       // g'olib nima oladi
    case Penalty = 'penalty';   // odatlarning chegaradan kamini bajargan nima qiladi
}
