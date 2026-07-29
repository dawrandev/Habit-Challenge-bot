<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Chat = matn + voqealar tasmasi — SPEC §8.
 */
class ChatService
{
    public function __construct(
        private readonly BattleAccess $access,
    ) {}

    /**
     * @return Collection<int, ChatMessage>
     */
    public function messages(User $user, int $battleId): Collection
    {
        $this->assertParticipant($user, $battleId);

        return ChatMessage::where('battle_id', $battleId)->orderBy('created_at')->get();
    }

    public function post(User $user, int $battleId, string $text): ChatMessage
    {
        $this->assertParticipant($user, $battleId);

        $text = trim($text);
        if ($text === '') {
            throw new BadRequestHttpException("Bo'sh xabar");
        }

        $message = ChatMessage::create([
            'battle_id' => $battleId,
            'sender_id' => $user->id,
            'text' => Str::limit($text, 2000, ''),
        ]);

        // Chat faqat ilovada — har xabarda bot'ga spam yubormaymiz.
        // Bot bildirishnomalari faqat muhim hodisalar uchun (taklif, tekshiruv).

        return $message;
    }

    private function assertParticipant(User $user, int $battleId): void
    {
        if (! $this->access->isParticipant($battleId, $user->id)) {
            throw new AccessDeniedHttpException("Ruxsat yo'q");
        }
    }
}
