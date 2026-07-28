<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompletionStatus;
use App\Models\Completion;
use App\Models\User;
use App\Services\Telegram\NotificationService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Nizo ochish — SPEC §5, §12–13.
 * Rad etilgan hisobot qayta ko'rish uchun pending'ga qaytariladi.
 */
class DisputeService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly BattleAccess $access,
    ) {}

    public function open(User $user, int $completionId): Completion
    {
        $completion = Completion::with('challenge')->find($completionId)
            ?? throw new NotFoundHttpException('Hisobot topilmadi');

        if ($completion->user_id !== $user->id) {
            throw new AccessDeniedHttpException("Faqat o'z hisobotingga nizo ocha olasan");
        }
        if ($completion->status !== CompletionStatus::Rejected) {
            throw new ConflictHttpException('Faqat rad etilgan hisobotga nizo');
        }

        $completion->disputes()->create(['opened_by' => $user->id]);
        $completion->update(['status' => CompletionStatus::Pending, 'resolved_at' => null]);

        $this->notifications->notify(
            $this->access->otherTelegramIds($completion->challenge->battle_id, $user->id),
            "⚑ {$user->first_name} qaroringga nizo ochdi — qayta ko'rib chiq",
        );

        return $completion;
    }
}
