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
use App\Support\LateWindow;
use Carbon\CarbonImmutable;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

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

    /**
     * @param  string|null  $day  'Y-m-d'; null — bugun. Kechagi kun ham mumkin (SPEC §18).
     */
    public function submit(
        User $user,
        int $challengeId,
        string $contents,
        string $filename = 'proof.jpg',
        ?string $note = null,
        ?string $day = null,
    ): Completion {
        $challenge = Challenge::find($challengeId)
            ?? throw new NotFoundHttpException('Challenge topilmadi');

        $context = $challenge->context();

        if (! $context->canSubmit($user->id)) {
            throw new AccessDeniedHttpException('Bu challenge bo\'yicha isbot yubora olmaysan');
        }
        if (! $context->isOpen()) {
            throw new ConflictHttpException('Yakunlangan — endi isbot qabul qilinmaydi');
        }

        $today = Clock::todayLocal()->toDateString();
        $day ??= $today;
        $late = $day !== $today;

        if ($late) {
            $this->assertLateAllowed($challenge, $user, $day);
        }
        if (! $context->coversDay($day)) {
            throw new ConflictHttpException('Bu kun davrga kirmaydi');
        }

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
        $when = $late ? ' kechagi ('.CarbonImmutable::parse($day)->format('d.m').')' : ' yangi';
        $this->notifications->notify(
            $context->memberTelegramIds($user->id),
            "📸 {$user->first_name}{$when} hisobot yubordi — tekshir!",
        );

        return $completion;
    }

    /**
     * O'tgan kun uchun isbot: faqat oyna ichida, faqat o'sha kuni navbatda
     * bo'lgan odatga va faqat hali hal bo'lmagan (yuborilmagan / rad etilgan) kunga.
     */
    private function assertLateAllowed(Challenge $challenge, User $user, string $day): void
    {
        if (! LateWindow::accepts($day)) {
            throw new UnprocessableEntityHttpException('Faqat kechagi kun uchun isbot yuborish mumkin');
        }

        $date = CarbonImmutable::parse($day);
        if ($challenge->pending
            || ! $challenge->active
            || $challenge->start_date->toDateString() > $day
            || ! $challenge->cadence->isDue($date, $challenge->weekdaysList())) {
            throw new UnprocessableEntityHttpException('Bu odat o\'sha kuni navbatda emas edi');
        }

        $existing = Completion::where('challenge_id', $challenge->id)
            ->where('user_id', $user->id)
            ->whereDate('day', $day)
            ->first();

        if ($existing !== null && ! in_array($existing->status, [CompletionStatus::Missed, CompletionStatus::Rejected], true)) {
            throw new ConflictHttpException('Bu kun uchun isbot allaqachon yuborilgan');
        }
    }
}
