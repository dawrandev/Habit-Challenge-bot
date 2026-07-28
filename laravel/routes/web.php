<?php

declare(strict_types=1);

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;

/*
 * React Mini App (SPA) — public/index.html.
 * API '/api/*' alohida (routes/api.php). Statik fayllar (assets) to'g'ridan-to'g'ri beriladi.
 */
$spa = fn (): Response => response(
    (string) file_get_contents(public_path('index.html')),
    200,
    ['Content-Type' => 'text/html'],
);

Route::get('/', $spa);
Route::fallback($spa);
