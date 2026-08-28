<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Missiyadagi rol — asimmetrik: faqat ega bajaradi, faqat guvoh tekshiradi.
 */
enum QuestRole: string
{
    case Owner = 'owner';       // bajaruvchi
    case Witness = 'witness';   // guvoh (tekshiruvchi)
}
