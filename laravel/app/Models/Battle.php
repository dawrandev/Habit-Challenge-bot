<?php

declare(strict_types=1);

namespace App\Models;

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
class Battle extends Model
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
}
