<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ProofContext;
use App\Enums\BattleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $title
 * @property BattleStatus $status
 * @property int $period_days
 * @property Carbon $start_date
 * @property Carbon $end_date
 * @property string $timezone
 * @property int $created_by
 * @property int|null $winner_id
 * @property string $invite_token
 */
class Battle extends Model implements ProofContext
{
    protected $fillable = [
        'title', 'status', 'period_days', 'start_date', 'end_date',
        'timezone', 'created_by', 'winner_id', 'invite_token',
    ];

    protected function casts(): array
    {
        return [
            'status' => BattleStatus::class,
            'start_date' => 'date:Y-m-d',
            'end_date' => 'date:Y-m-d',
        ];
    }

    public function participants(): HasMany
    {
        return $this->hasMany(BattleParticipant::class);
    }

    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    // --- ProofContext -------------------------------------------------------

    public function contextKey(): string
    {
        return 'battle';
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
        if ($this->relationLoaded('participants')) {
            return $this->participants->contains('user_id', $userId);
        }

        return $this->participants()->where('user_id', $userId)->exists();
    }

    /** Duelda simmetrik: har ikkala ishtirokchi bajaradi. */
    public function canSubmit(int $userId): bool
    {
        return $this->hasMember($userId);
    }

    /** Duelda kesishgan tekshiruv: raqib tekshiradi, o'zini hech kim tekshirmaydi. */
    public function canVerify(int $userId, int $submitterId): bool
    {
        return $userId !== $submitterId
            && $this->hasMember($userId)
            && $this->hasMember($submitterId);
    }

    /**
     * @return array<int>
     */
    public function memberTelegramIds(int $excludeUserId): array
    {
        return $this->participants()
            ->where('user_id', '!=', $excludeUserId)
            ->with('user')
            ->get()
            ->pluck('user.telegram_id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }
}
