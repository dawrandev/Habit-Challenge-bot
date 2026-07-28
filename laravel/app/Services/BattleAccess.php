<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BattleParticipant;

/**
 * Battle ruxsati va ishtirokchi yordamchilari.
 */
class BattleAccess
{
    public function isParticipant(int $battleId, int $userId): bool
    {
        return BattleParticipant::where('battle_id', $battleId)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Boshqa ishtirokchilarning telegram_id'lari (notifikatsiya uchun).
     *
     * @return array<int>
     */
    public function otherTelegramIds(int $battleId, int $excludeUserId): array
    {
        return BattleParticipant::where('battle_id', $battleId)
            ->where('user_id', '!=', $excludeUserId)
            ->with('user')
            ->get()
            ->pluck('user.telegram_id')
            ->filter()
            ->values()
            ->all();
    }
}
