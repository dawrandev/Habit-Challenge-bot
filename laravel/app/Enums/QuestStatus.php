<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Missiya holati.
 *
 * Battle'dan farqi: missiya guvohsiz ham darhol boshlanadi (Active).
 * Guvoh keyin qo'shiladi; ungacha isbotlar 24s dan so'ng avto-tasdiqlanadi (SPEC §5).
 */
enum QuestStatus: string
{
    case Active = 'active';
    case Finished = 'finished';
    case Abandoned = 'abandoned';   // egasi yarim yo'lda to'xtatdi

    public function isOpen(): bool
    {
        return $this === self::Active;
    }
}
