<?php

declare(strict_types=1);

namespace App\Models;

use App\Contracts\ProofContext;
use App\Enums\Cadence;
use App\Enums\ProofType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * @property int $id
 * @property int|null $battle_id
 * @property int|null $quest_id
 * @property string|null $template_key
 * @property string $name
 * @property string $icon
 * @property Cadence $cadence
 * @property array<int> $weekdays
 * @property string|null $remind_at odat vaqti 'HH:MM' — eslatma shu paytda (SPEC §18)
 * @property Carbon $start_date
 * @property bool $active
 */
class Challenge extends Model
{
    protected $fillable = [
        'battle_id', 'quest_id', 'template_key', 'name', 'description', 'icon',
        'cadence', 'weekdays', 'start_date', 'active',
        'pending', 'proposed_by', 'proof_type', 'remind_at',
    ];

    protected function casts(): array
    {
        return [
            'cadence' => Cadence::class,
            'weekdays' => 'array',
            'start_date' => 'date:Y-m-d',
            'active' => 'boolean',
            'pending' => 'boolean',
            'proof_type' => ProofType::class,
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(Battle::class);
    }

    public function quest(): BelongsTo
    {
        return $this->belongsTo(Quest::class);
    }

    /**
     * Challenge tegishli bo'lgan konteyner — duel yoki missiya.
     *
     * Invariant: aynan bittasi to'ldirilgan bo'lishi shart. Yetim challenge
     * (ikkalasi ham null) — ma'lumot buzilishi, jimgina o'tkazib yubormaymiz.
     */
    public function context(): ProofContext
    {
        // Magic accessor: eager-load qilingan bo'lsa o'shani oladi, aks holda
        // bir marta lazy-load qilib keshlaydi (navbat ro'yxatida N+1 bo'lmasin).
        $context = $this->quest_id !== null ? $this->quest : $this->battle;

        if (! $context instanceof ProofContext) {
            throw new RuntimeException("Challenge #{$this->id} konteynersiz (battle_id ham, quest_id ham yo'q)");
        }

        return $context;
    }

    /**
     * Bot xabarlari uchun nom. Shablon nomlari UI'da tarjima qilinadi,
     * bot esa hozircha o'zbekcha yozadi (README: bot i18n — keyingi ish).
     */
    public function label(): string
    {
        if ($this->name !== '') {
            return $this->name;
        }

        return match ($this->template_key) {
            'read' => "O'qish",
            'sport' => 'Sport',
            'earlyRise' => 'Erta turish',
            default => (string) ($this->template_key ?? 'Odat'),
        };
    }

    public function belongsToQuest(): bool
    {
        return $this->quest_id !== null;
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
