<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Telegram\PhotoService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Foto/video proxy (va'da isbotlari — SPEC §17).
 *
 * `/photo` dan farqi: fayl diskdan beriladi, shuning uchun Range so'rovlari
 * ishlaydi — video oldinga suriladi va iOS webview'da o'ynaydi.
 */
class MediaController extends Controller
{
    public function __construct(private readonly PhotoService $media) {}

    public function show(string $fileId): BinaryFileResponse
    {
        // DEV placeholder — Telegram'da bunday fayl yo'q
        if (str_starts_with($fileId, 'dev-')) {
            abort(404);
        }

        $path = $this->media->cachedPath($fileId) ?? abort(404);

        return response()->file($path, [
            'Content-Type' => mime_content_type($path) ?: 'application/octet-stream',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
