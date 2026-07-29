<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BattleController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CompletionController;
use App\Http\Controllers\Api\DevController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Ochiq (auth'siz)
Route::get('/photo/{fileId}', [PhotoController::class, 'show']);
Route::post('/telegram/webhook', [WebhookController::class, 'handle']);

// Telegram initData auth
Route::middleware('tg.auth')->group(function () {
    Route::get('/me', MeController::class);

    Route::get('/battles', [BattleController::class, 'index']);
    Route::post('/battles', [BattleController::class, 'store']);
    Route::post('/battles/{token}/accept', [BattleController::class, 'accept']);
    Route::get('/battles/{battle}', [BattleController::class, 'show']);
    Route::get('/battles/{battle}/today', [BattleController::class, 'today']);
    Route::post('/battles/{battle}/challenges', [BattleController::class, 'addChallenge']);
    Route::get('/battles/{battle}/messages', [ChatController::class, 'index']);
    Route::post('/battles/{battle}/messages', [ChatController::class, 'store']);

    Route::post('/completions', [CompletionController::class, 'store']);
    Route::post('/completions/{completion}/verify', [CompletionController::class, 'verify']);
    Route::post('/completions/{completion}/dispute', [CompletionController::class, 'dispute']);
    Route::get('/verify-queue', [CompletionController::class, 'queue']);

    Route::post('/dev/seed', [DevController::class, 'seed']);
});
