<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Missiya yakuni — g'olib emas, maqsadga yetildimi.
 */
enum QuestOutcome: string
{
    case Achieved = 'achieved';   // bajarish foizi >= maqsad
    case Missed = 'missed';       // maqsadga yetmadi
}
