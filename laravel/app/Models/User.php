<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Telegram foydalanuvchisi — SPEC §2 (login/parol yo'q).
 *
 * @property int $id
 * @property int $telegram_id
 * @property string|null $username
 * @property string $first_name
 * @property string|null $photo_url
 * @property string $language
 */
class User extends Model
{
    protected $fillable = [
        'telegram_id',
        'username',
        'first_name',
        'photo_url',
        'language',
    ];

    protected function casts(): array
    {
        return [
            'telegram_id' => 'integer',
        ];
    }

    public function battles(): BelongsToMany
    {
        return $this->belongsToMany(Battle::class, 'battle_participants')
            ->withPivot(['accepted', 'score'])
            ->withTimestamps();
    }
}
