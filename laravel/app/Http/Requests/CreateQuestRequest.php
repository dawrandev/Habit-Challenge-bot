<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Cadence;
use App\Enums\ProofType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CreateQuestRequest extends FormRequest
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
            'period_days' => ['required', 'integer', 'min:1', 'max:365'],
            'start_tomorrow' => ['boolean'],
            'goal_percent' => ['required', 'integer', 'min:10', 'max:100'],
            'challenges' => ['required', 'array', 'min:1', 'max:8'],
            'challenges.*.template_key' => ['nullable', 'string', 'max:40'],
            'challenges.*.name' => ['nullable', 'string', 'max:120'],
            'challenges.*.description' => ['nullable', 'string', 'max:200'],
            'challenges.*.icon' => ['nullable', 'string', 'max:16'],
            'challenges.*.cadence' => ['required', Rule::enum(Cadence::class)],
            'challenges.*.proof_type' => ['nullable', Rule::enum(ProofType::class)],
            'challenges.*.weekdays' => ['array'],
            'challenges.*.weekdays.*' => ['integer', 'between:0,6'],
        ];
    }
}
