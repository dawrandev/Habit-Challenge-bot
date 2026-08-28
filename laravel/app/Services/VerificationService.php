<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompletionStatus;
use App\Enums\DisputeStatus;
use App\Models\BattleParticipant;
use App\Models\Completion;
use App\Models\Dispute;
use App\Models\Quest;
use App\Models\User;
use App\Services\Telegram\NotificationService;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Tekshiruv (tasdiq / rad) + nizoni hal qilish — SPEC §5.
 *
 * Kim tekshira olishini ProofContext hal qiladi:
 *   duel    — raqib (kesishgan tekshiruv)
 *   missiya — faqat guvoh (ega o'zini tekshira olmaydi)
 */
class VerificationService
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function verify(User $verifier, int $completionId, bool $approve): Completion
    {
        $completion = Completion::with(['challenge', 'user'])->find($completionId)
            ?? throw new NotFoundHttpException('Hisobot topilmadi');

        $context = $completion->challenge->context();

        // Bitta tekshiruv o'z ichiga oladi: o'zini tekshirmaslik, a'zolik va rol.
        if (! $context->canVerify($verifier->id, $completion->user_id)) {
            throw new AccessDeniedHttpException('Bu hisobotni tekshirish huquqing yo\'q');
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
     * Foydalanuvchi tekshirishi kerak bo'lgan hisobotlar (Faoliyat ekrani).
     *
     * Ikki manbadan yig'iladi: duel raqiblari + guvohlik qilayotgan missiyalar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function queueFor(User $user): array
    {
        $battleIds = BattleParticipant::where('user_id', $user->id)->pluck('battle_id');
        $questIds = Quest::where('witness_id', $user->id)->pluck('id');

        if ($battleIds->isEmpty() && $questIds->isEmpty()) {
            return [];
        }

        $pending = Completion::query()
            ->with(['challenge.battle.participants', 'challenge.quest', 'user'])
            ->whereHas('challenge', fn ($q) => $q
                ->whereIn('battle_id', $battleIds)
                ->orWhereIn('quest_id', $questIds))
            ->where('user_id', '!=', $user->id)
            ->where('status', CompletionStatus::Pending->value)
            ->orderBy('submitted_at')
            ->get();

        return $pending
            ->filter(function (Completion $completion) use ($user) {
                $context = $completion->challenge->context();

                // Yakunlangan duel/missiya navbatda turmasin.
                return $context->isOpen()
                    && $context->canVerify($user->id, $completion->user_id);
            })
            ->map(function (Completion $completion) {
                $challenge = $completion->challenge;
                $context = $challenge->context();

                return [
                    'completion' => $completion,
                    'challenge' => $challenge,
                    'rival' => $completion->user,
                    'context' => [
                        'key' => $context->contextKey(),
                        'id' => $context->contextId(),
                        'title' => $context->contextTitle(),
                    ],
                ];
            })
            ->values()
            ->all();
    }
}
