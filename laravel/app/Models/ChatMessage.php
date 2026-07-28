<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $battle_id
 * @property int|null $sender_id
 * @property string $kind
 * @property string $text
 */
class ChatMessage extends Model
{
    protected $fillable = ['battle_id', 'sender_id', 'kind', 'text'];

    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
