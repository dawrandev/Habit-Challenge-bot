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
 * @property int|null $habits_done yakunda muhrlanadi (SPEC §17)
 * @property int|null $habits_expected
 * @property bool|null $penalized
 */
class BattleParticipant extends Model
{
    protected $fillable = [
        'battle_id', 'user_id', 'accepted', 'score',
        'habits_done', 'habits_expected', 'penalized',
    ];

    protected function casts(): array
    {
        return [
            'accepted' => 'boolean',
            'score' => 'float',
            'habits_done' => 'integer',
            'habits_expected' => 'integer',
            'penalized' => 'boolean',
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
