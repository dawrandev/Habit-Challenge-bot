<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $battle_id
 * @property int $user_id
 * @property bool $accepted
 * @property float $score
 */
class BattleParticipant extends Model
{
    protected $fillable = ['battle_id', 'user_id', 'accepted', 'score'];

    protected function casts(): array
    {
        return [
            'accepted' => 'boolean',
            'score' => 'float',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
