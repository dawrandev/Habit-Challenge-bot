<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Cadence;
use App\Enums\ProofType;
use App\Support\DateRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateBattleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            // Davr endi aynan sanalar bilan belgilanadi (SPEC §3 "erkin sana")
            ...DateRange::rules(),
            'challenges' => ['required', 'array', 'min:1', 'max:8'],
            'challenges.*.template_key' => ['nullable', 'string', 'max:40'],
            'challenges.*.name' => ['nullable', 'string', 'max:120'],
            'challenges.*.description' => ['nullable', 'string', 'max:200'],
            'challenges.*.icon' => ['nullable', 'string', 'max:16'],
            'challenges.*.cadence' => ['required', Rule::enum(Cadence::class)],
            'challenges.*.proof_type' => ['nullable', Rule::enum(ProofType::class)],
            'challenges.*.weekdays' => ['array'],
            'challenges.*.weekdays.*' => ['integer', 'between:0,6'],
            'challenges.*.remind_at' => ['nullable', 'date_format:H:i'],
            // Garov (SPEC §17) — ixtiyoriy API darajasida (eski mijozlar), lekin
            // berilsa, ikkala variant ham bo'lishi shart
            'stakes' => ['nullable', 'array'],
            'stakes.prize' => ['required_with:stakes', 'string', 'min:2', 'max:200'],
            'stakes.penalty' => ['required_with:stakes', 'string', 'min:2', 'max:200'],
        ];
    }
}
