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
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Hisobot yuborish (jonli kamera → rasm → file_id) — SPEC §5, §10.
 */
class CompletionService
{
    public function __construct(
        private readonly PhotoService $photos,
        private readonly NotificationService $notifications,
        private readonly BattleAccess $access,
    ) {}

    public function submit(User $user, int $challengeId, string $contents, string $filename = 'proof.jpg', ?string $note = null): Completion
    {
        $challenge = Challenge::find($challengeId)
            ?? throw new NotFoundHttpException('Challenge topilmadi');

        if (! $this->access->isParticipant($challenge->battle_id, $user->id)) {
            throw new AccessDeniedHttpException('Sen bu battle ishtirokchisi emassan');
        }

        $day = Clock::todayLocal()->toDateString();
        $fileId = $this->photos->store($contents, $filename);

        // Rad etilgan bo'lsa ham qayta yuborish mumkin
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

        $this->notifications->notify(
            $this->access->otherTelegramIds($challenge->battle_id, $user->id),
            "📸 {$user->first_name} yangi hisobot yubordi — tekshir!",
        );

        return $completion;
    }
}
