<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\PhotoService;
use Symfony\Component\HttpFoundation\Response;

/**
 * Rasm proxy — bot token oshkor bo'lmaydi (SPEC §10).
 */
class PhotoController extends Controller
{
    public function __construct(private readonly PhotoService $photos) {}

    public function show(string $fileId): Response
    {
        $data = $this->photos->fetch($fileId);

        if ($data === null) {
            abort(404);
        }

        return response($data, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
