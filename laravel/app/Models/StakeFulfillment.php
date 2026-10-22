<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\StakeKind;
use Illuminate\Database\Eloquent\Model;

/**
 * Va'da (yutuq yoki jazo) bajarilganining foto/video isboti (SPEC §17).
 *
 * @property int $id
 * @property int $battle_id
 * @property StakeKind $kind
 * @property int $debtor_id
 * @property string $file_id
 * @property string $media_type
 * @property string|null $note
 */
class StakeFulfillment extends Model
{
    public const PHOTO = 'photo';

    public const VIDEO = 'video';

    protected $fillable = ['battle_id', 'kind', 'debtor_id', 'file_id', 'media_type', 'note'];

    protected function casts(): array
    {
        return ['kind' => StakeKind::class];
    }
}
