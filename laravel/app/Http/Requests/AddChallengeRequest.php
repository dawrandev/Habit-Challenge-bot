<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\Cadence;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AddChallengeRequest extends FormRequest
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
            'template_key' => ['nullable', 'string', 'max:40'],
            'name' => ['nullable', 'string', 'max:120'],
            'icon' => ['nullable', 'string', 'max:16'],
            'cadence' => ['required', Rule::enum(Cadence::class)],
            'weekdays' => ['array'],
            'weekdays.*' => ['integer', 'between:0,6'],
        ];
    }
}
