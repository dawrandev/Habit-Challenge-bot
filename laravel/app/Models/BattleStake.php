<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StakeDecision;
use App\Enums\StakeKind;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bitta o'yinchining bitta garov turi bo'yicha varianti (SPEC §17).
 *
 * @property int $id
 * @property int $battle_id
 * @property StakeKind $kind
 * @property int $proposed_by
 * @property string $text
 * @property StakeDecision|null $chosen_via
 */
class BattleStake extends Model
{
    protected $fillable = ['battle_id', 'kind', 'proposed_by', 'text', 'chosen_via'];

    protected function casts(): array
    {
        return [
            'kind' => StakeKind::class,
            'chosen_via' => StakeDecision::class,
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }

    public function isChosen(): bool
    {
        return $this->chosen_via !== null;
    }
}
