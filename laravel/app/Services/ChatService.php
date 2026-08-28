<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\ProofContext;
use App\Models\ChatMessage;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Chat = matn + voqealar tasmasi — SPEC §8.
 *
 * Duel va missiya uchun bir xil ishlaydi; qaysi ustunga yozish kerakligini
 * konteynerning o'zi aytadi ('battle_id' | 'quest_id').
 */
class ChatService
{
    /**
     * @return Collection<int, ChatMessage>
     */
    public function messages(User $user, ProofContext $context): Collection
    {
        $this->assertMember($user, $context);

        return ChatMessage::query()
            ->where($this->column($context), $context->contextId())
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    public function post(User $user, ProofContext $context, string $text): ChatMessage
    {
        $this->assertMember($user, $context);

        $text = trim($text);
        if ($text === '') {
            throw new BadRequestHttpException("Bo'sh xabar");
        }

        // Chat faqat ilovada — har xabarda bot'ga spam yubormaymiz.
        // Bot bildirishnomalari faqat muhim hodisalar uchun (taklif, tekshiruv).
        return ChatMessage::create([
            $this->column($context) => $context->contextId(),
            'sender_id' => $user->id,
            'kind' => 'text',
            'text' => Str::limit($text, 2000, ''),
        ]);
    }

    /**
     * Tizim voqeasi (sender_id = null) — chatda kulrang qatorcha bo'lib chiqadi.
     */
    public function postEvent(ProofContext $context, string $text): ChatMessage
    {
        return ChatMessage::create([
            $this->column($context) => $context->contextId(),
            'sender_id' => null,
            'kind' => 'event',
            'text' => Str::limit($text, 2000, ''),
        ]);
    }

    private function column(ProofContext $context): string
    {
        return $context->contextKey().'_id';
    }

    private function assertMember(User $user, ProofContext $context): void
    {
        if (! $context->hasMember($user->id)) {
            throw new AccessDeniedHttpException("Ruxsat yo'q");
        }
    }
}
