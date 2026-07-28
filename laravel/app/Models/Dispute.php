<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DisputeStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $completion_id
 * @property int $opened_by
 * @property DisputeStatus $status
 * @property Carbon|null $resolved_at
 */
class Dispute extends Model
{
    protected $fillable = ['completion_id', 'opened_by', 'status', 'resolved_at'];

    protected function casts(): array
    {
        return [
            'status' => DisputeStatus::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function completion(): BelongsTo
    {
        return $this->belongsTo(Completion::class);
    }
}
