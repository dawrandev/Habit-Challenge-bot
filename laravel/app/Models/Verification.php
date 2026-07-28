<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $completion_id
 * @property int $verifier_id
 * @property bool $approve
 * @property bool $is_dispute_review
 */
class Verification extends Model
{
    protected $fillable = ['completion_id', 'verifier_id', 'approve', 'is_dispute_review'];

    protected function casts(): array
    {
        return [
            'approve' => 'boolean',
            'is_dispute_review' => 'boolean',
        ];
    }

    public function completion(): BelongsTo
    {
        return $this->belongsTo(Completion::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_id');
    }
}
