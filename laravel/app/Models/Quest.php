<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ProofContext;
use App\Enums\QuestOutcome;
use App\Enums\QuestRole;
use App\Enums\QuestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Missiya — yakka odat yo'li, tashqi guvoh bilan.
 *
 * Asimmetriya shu yerda yashaydi: `owner_id` bajaradi, `witness_id` tekshiradi.
 * Guvohga challenge'larni bajarish SHART EMAS — u faqat guvohlik qiladi.
 *
 * @property int $id
 * @property string $title
 * @property QuestStatus $status
 * @property int $owner_id
 * @property int|null $witness_id
 * @property int $period_days
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $timezone
 * @property int $goal_percent
 * @property QuestOutcome|null $outcome
 * @property string $invite_token
 */
class Quest extends Model implements ProofContext
{
    protected $fillable = [
        'title', 'status', 'owner_id', 'witness_id', 'period_days',
        'start_date', 'end_date', 'timezone', 'goal_percent',
        'outcome', 'invite_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => QuestStatus::class,
            'outcome' => QuestOutcome::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
            'goal_percent' => 'integer',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function witness(): BelongsTo
    {
        return $this->belongsTo(User::class, 'witness_id');
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function roleOf(int $userId): ?QuestRole
    {
        return match ($userId) {
            $this->owner_id => QuestRole::Owner,
            $this->witness_id => QuestRole::Witness,
            default => null,
        };
    }

    public function hasWitness(): bool
    {
        return $this->witness_id !== null;
    }

    // --- ProofContext -------------------------------------------------------

    public function contextKey(): string
    {
        return 'quest';
    }

    public function contextId(): int
    {
        return $this->id;
    }

    public function contextTitle(): string
    {
        return $this->title;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function hasMember(int $userId): bool
    {
        return $this->roleOf($userId) !== null;
    }

    /** Faqat ega bajaradi — missiyaning butun mohiyati shu. */
    public function canSubmit(int $userId): bool
    {
        return $this->owner_id === $userId;
    }

    /** Faqat guvoh tekshiradi, va faqat eganing isbotini (o'zini hech kim tekshirmaydi). */
    public function canVerify(int $userId, int $submitterId): bool
    {
        return $this->witness_id !== null
            && $this->witness_id === $userId
            && $submitterId === $this->owner_id
            && $userId !== $submitterId;
    }

    /**
     * @return array<int>
     */
    public function memberTelegramIds(int $excludeUserId): array
    {
        $this->loadMissing(['owner', 'witness']);

        return collect([$this->owner, $this->witness])
            ->filter(fn (?User $u) => $u !== null && $u->id !== $excludeUserId)
            ->pluck('telegram_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
