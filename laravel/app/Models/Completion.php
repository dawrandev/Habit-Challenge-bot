<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CompletionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $challenge_id
 * @property int $user_id
 * @property Carbon $day
 * @property CompletionStatus $status
 * @property string|null $file_id
 * @property string|null $note
 * @property Carbon|null $submitted_at
 * @property Carbon|null $resolved_at
 */
class Completion extends Model
{
    protected $fillable = [
        'challenge_id', 'user_id', 'day', 'status',
        'file_id', 'note', 'submitted_at', 'resolved_at',
    ];

    /**
     * Yopilish paytida hali tekshirilmagan isbotlarni tasdiqlaydi.
     *
     * Oxirgi kun(lar)ning isboti tekshiruvchi ulgurmay qolgani uchun hisobdan
     * chiqib ketmasin — tekshirmaslik o'yinchining aybi emas (SPEC §5).
     *
     * @param  array<int>  $challengeIds
     */
    public static function approvePendingOnClose(array $challengeIds): int
    {
        if ($challengeIds === []) {
            return 0;
        }

        return static::query()
            ->whereIn('challenge_id', $challengeIds)
            ->where('status', CompletionStatus::Pending->value)
            ->update([
                'status' => CompletionStatus::AutoApproved->value,
                'resolved_at' => now(),
            ]);
    }

    protected function casts(): array
    {
        return [
            'day' => 'date:Y-m-d',
            'status' => CompletionStatus::class,
            'submitted_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function challenge(): BelongsTo
    {
        return $this->belongsTo(Challenge::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(Verification::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }
}
