<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Cadence;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $battle_id
 * @property string|null $template_key
 * @property string $name
 * @property string $icon
 * @property Cadence $cadence
 * @property array<int> $weekdays
 * @property Carbon $start_date
 * @property bool $active
 */
class Challenge extends Model
{
    protected $fillable = [
        'battle_id', 'template_key', 'name', 'icon',
        'cadence', 'weekdays', 'start_date', 'active',
    ];

    protected function casts(): array
    {
        return [
            'cadence' => Cadence::class,
            'weekdays' => 'array',
            'start_date' => 'date:Y-m-d',
            'active' => 'boolean',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }

    public function completions(): HasMany
    {
        return $this->hasMany(Completion::class);
    }

    /**
     * @return array<int>
     */
    public function weekdaysList(): array
    {
        return $this->weekdays ?? [];
    }
}
