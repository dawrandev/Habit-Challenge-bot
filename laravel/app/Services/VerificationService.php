<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompletionStatus;
use App\Enums\DisputeStatus;
use App\Models\BattleParticipant;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\Dispute;
use App\Models\User;
use App\Services\Telegram\NotificationService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tekshiruv (tasdiq / rad) + nizoni hal qilish — SPEC §5.
 */
class VerificationService
{
    public function __construct(
        private readonly NotificationService $notifications,
        private readonly BattleAccess $access,
    ) {}

    public function verify(User $verifier, int $completionId, bool $approve): Completion
    {
        $completion = Completion::with('challenge')->find($completionId)
            ?? throw new NotFoundHttpException('Hisobot topilmadi');

        if ($completion->user_id === $verifier->id) {
            throw new AccessDeniedHttpException("O'z hisobotingni tekshira olmaysan");
        }
        if (! $this->access->isParticipant($completion->challenge->battle_id, $verifier->id)) {
            throw new AccessDeniedHttpException('Ruxsat yo\'q');
        }
        if ($completion->status !== CompletionStatus::Pending) {
            throw new ConflictHttpException('Bu hisobot allaqachon hal qilingan');
        }

        $dispute = Dispute::where('completion_id', $completion->id)
            ->where('status', DisputeStatus::Open->value)
            ->first();

        $completion->verifications()->create([
            'verifier_id' => $verifier->id,
            'approve' => $approve,
            'is_dispute_review' => $dispute !== null,
        ]);

        $completion->update([
            'status' => $approve ? CompletionStatus::Approved : CompletionStatus::Rejected,
            'resolved_at' => now(),
        ]);

        if ($dispute !== null) {
            $dispute->update([
                'status' => $approve ? DisputeStatus::ResolvedApproved : DisputeStatus::ResolvedUpheld,
                'resolved_at' => now(),
            ]);
        }

        $verb = $approve ? 'tasdiqladi ✓' : 'rad etdi ✕';
        $this->notifications->notify(
            [$completion->user->telegram_id],
            "{$verifier->first_name} hisobotingni {$verb}",
        );

        return $completion;
    }

    /**
     * Boshqa ishtirokchilarning tekshiruv kutayotgan pending hisobotlari (Faoliyat).
     *
     * @return array<int, array{completion: Completion, challenge: Challenge, rival: User}>
     */
    public function queueFor(User $user): array
    {
        $battleIds = BattleParticipant::where('user_id', $user->id)->pluck('battle_id');

        $pending = Completion::query()
            ->with(['challenge', 'user'])
            ->whereHas('challenge', fn ($q) => $q->whereIn('battle_id', $battleIds))
            ->where('user_id', '!=', $user->id)
            ->where('status', CompletionStatus::Pending->value)
            ->get();

        return $pending->map(fn (Completion $c) => [
            'completion' => $c,
            'challenge' => $c->challenge,
            'rival' => $c->user,
        ])->all();
    }
}
