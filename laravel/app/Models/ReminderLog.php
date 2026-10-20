<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Yuborilgan eslatma — takror yubormaslik uchun (SPEC §18).
 *
 * @property int $id
 * @property int $user_id
 * @property string $kind habit | evening | late
 * @property string $ref
 * @property string $day
 */
class ReminderLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'kind', 'ref', 'day'];
}
