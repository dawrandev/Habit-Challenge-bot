<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class FulfillPromiseRequest extends FormRequest
{
    /** Telegram getFile 20MB dan kattasini qaytarmaydi — proxy ko'rsata olmay qoladi. */
    public const MAX_KB = 20480;

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
            'file' => [
                'required',
                'file',
                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm',
                'max:'.self::MAX_KB,
            ],
            'note' => ['nullable', 'string', 'max:280'],
        ];
    }
}
