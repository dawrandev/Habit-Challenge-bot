<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Isbot konteyneri — challenge qaysi "maydon"ga tegishli bo'lsa, o'sha.
 *
 * Ikki amalga oshirish bor va ular ATAYLAB asimmetrik:
 *
 *   Battle (duel)   — ikkala ishtirokchi bajaradi, bir-birini tekshiradi.
 *   Quest (missiya) — faqat ega bajaradi, faqat guvoh tekshiradi.
 *
 * Shu interfeys tufayli isbot quvuri (CompletionService, VerificationService,
 * DisputeService, ChatService) ikkala rejimni ham bilmasdan xizmat qiladi.
 */
interface ProofContext
{
    /** 'battle' | 'quest' — bildirishnoma va havolalar uchun. */
    public function contextKey(): string;

    public function contextId(): int;

    /** Ro'yxat/bildirishnomada ko'rinadigan nom. */
    public function contextTitle(): string;

    /** Yangi isbot/xabar qabul qiladigan holatdami. */
    public function isOpen(): bool;

    /** Foydalanuvchi umuman shu konteynerni ko'ra oladimi. */
    public function hasMember(int $userId): bool;

    /** Kim isbot yubora oladi (missiyada — faqat ega). */
    public function canSubmit(int $userId): bool;

    /** Kim $submitterId ning isbotini tekshira oladi (missiyada — faqat guvoh). */
    public function canVerify(int $userId, int $submitterId): bool;

    /**
     * Bildirishnoma uchun boshqa a'zolarning telegram_id'lari.
     *
     * @return array<int>
     */
    public function memberTelegramIds(int $excludeUserId): array;
}
