<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CompletionStatus;
use App\Models\Challenge;
use App\Models\Completion;
use App\Models\User;
use App\Services\Telegram\NotificationService;
use App\Services\Telegram\PhotoService;
use App\Support\Clock;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hisobot yuborish (jonli kamera → rasm → file_id) — SPEC §5, §10.
 *
 * Konteynerga BEFARQ: duelda ikkala o'yinchi, missiyada faqat ega yubora oladi —
 * qaysi biri ekanini ProofContext hal qiladi.
 */
class CompletionService
{
    public function __construct(
        private readonly PhotoService $photos,
        private readonly NotificationService $notifications,
    ) {}

    public function submit(User $user, int $challengeId, string $contents, string $filename = 'proof.jpg', ?string $note = null): Completion
    {
        $challenge = Challenge::find($challengeId)
            ?? throw new NotFoundHttpException('Challenge topilmadi');

        $context = $challenge->context();

        if (! $context->canSubmit($user->id)) {
            throw new AccessDeniedHttpException('Bu challenge bo\'yicha isbot yubora olmaysan');
        }
        if (! $context->isOpen()) {
            throw new ConflictHttpException('Yakunlangan — endi isbot qabul qilinmaydi');
        }

        $day = Clock::todayLocal()->toDateString();
        $fileId = $this->photos->store($contents, $filename);

        // Rad etilgan bo'lsa ham kun oxirigacha qayta yuborish mumkin (SPEC §4)
        $completion = Completion::updateOrCreate(
            ['challenge_id' => $challengeId, 'user_id' => $user->id, 'day' => $day],
            [
                'file_id' => $fileId,
                'note' => $note,
                'status' => CompletionStatus::Pending,
                'submitted_at' => now(),
                'resolved_at' => null,
            ],
        );

        // Missiyada guvoh hali qo'shilmagan bo'lsa — ro'yxat bo'sh, xabar ketmaydi.
        $this->notifications->notify(
            $context->memberTelegramIds($user->id),
            "📸 {$user->first_name} yangi hisobot yubordi — tekshir!",
        );

        return $completion;
    }
}
