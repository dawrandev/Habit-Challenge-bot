<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StakeKind;
use Illuminate\Database\Eloquent\Model;

/**
 * O'yinchining garov turi bo'yicha tanlovi (o'yinchi × tur bo'yicha bitta).
 *
 * @property int $id
 * @property int $battle_id
 * @property int $user_id
 * @property StakeKind $kind
 * @property int $stake_id
 */
class BattleStakeVote extends Model
{
    protected $fillable = ['battle_id', 'user_id', 'kind', 'stake_id'];

    protected function casts(): array
    {
        return ['kind' => StakeKind::class];
    }
}
