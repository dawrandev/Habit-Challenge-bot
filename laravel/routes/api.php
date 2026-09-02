<?php

declare(strict_types=1);

use App\Http\Controllers\Api\BattleController;
use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\CompletionController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\DayController;
use App\Http\Controllers\Api\DevController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\PhotoController;
use App\Http\Controllers\Api\QuestController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\WebhookController;
use Illuminate\Support\Facades\Route;

// Ochiq (auth'siz)
Route::get('/config', ConfigController::class);
Route::get('/day', DayController::class);
Route::get('/photo/{fileId}', [PhotoController::class, 'show'])->middleware('throttle:60,1');
Route::post('/telegram/webhook', [WebhookController::class, 'handle']);

// Telegram initData auth
Route::middleware(['tg.auth', 'throttle:120,1'])->group(function () {
    Route::get('/me', MeController::class);
    Route::get('/stats', StatsController::class);

    /*
     * ⚔️ Duel — 1v1, simmetrik: ikkalasi bajaradi, bir-birini tekshiradi.
     */
    Route::get('/battles', [BattleController::class, 'index']);
    Route::post('/battles', [BattleController::class, 'store']);
    Route::get('/battles/invite/{token}', [BattleController::class, 'invite']);
    Route::post('/battles/{token}/accept', [BattleController::class, 'accept']);
    Route::get('/battles/{battle}', [BattleController::class, 'show']);
    Route::patch('/battles/{battle}', [BattleController::class, 'update']);
    Route::delete('/battles/{battle}', [BattleController::class, 'destroy']);
    Route::get('/battles/{battle}/today', [BattleController::class, 'today']);
    Route::post('/battles/{battle}/challenges', [BattleController::class, 'addChallenge']);
    Route::patch('/battles/{battle}/challenges/{challenge}', [BattleController::class, 'updateChallenge']);
    Route::delete('/battles/{battle}/challenges/{challenge}', [BattleController::class, 'destroyChallenge']);
    Route::post('/battles/{battle}/challenges/{challenge}/accept', [BattleController::class, 'acceptChallenge']);
    Route::post('/battles/{battle}/challenges/{challenge}/reject', [BattleController::class, 'rejectChallenge']);
    Route::get('/battles/{battle}/messages', [ChatController::class, 'index']);
    Route::post('/battles/{battle}/messages', [ChatController::class, 'store']);

    /*
     * 🎯 Missiya — yakka odat yo'li, asimmetrik: ega bajaradi, guvoh tekshiradi.
     * Odat FAQAT eganiki — guvohga o'sha odat kerak emas.
     */
    Route::get('/quests', [QuestController::class, 'index']);
    Route::post('/quests', [QuestController::class, 'store']);
    Route::get('/quests/invite/{token}', [QuestController::class, 'invite']);
    Route::post('/quests/{token}/accept', [QuestController::class, 'accept']);
    Route::get('/quests/{quest}', [QuestController::class, 'show']);
    Route::patch('/quests/{quest}', [QuestController::class, 'update']);
    Route::delete('/quests/{quest}', [QuestController::class, 'destroy']);
    Route::post('/quests/{quest}/abandon', [QuestController::class, 'abandon']);
    Route::get('/quests/{quest}/today', [QuestController::class, 'today']);
    Route::post('/quests/{quest}/challenges', [QuestController::class, 'addChallenge']);
    Route::patch('/quests/{quest}/challenges/{challenge}', [QuestController::class, 'updateChallenge']);
    Route::delete('/quests/{quest}/challenges/{challenge}', [QuestController::class, 'destroyChallenge']);
    Route::get('/quests/{quest}/messages', [ChatController::class, 'questIndex']);
    Route::post('/quests/{quest}/messages', [ChatController::class, 'questStore']);

    /*
     * Isbot quvuri — duel va missiya uchun UMUMIY.
     * Kim yubora/tekshira olishini ProofContext hal qiladi.
     */
    Route::post('/completions', [CompletionController::class, 'store']);
    Route::post('/completions/{completion}/verify', [CompletionController::class, 'verify']);
    Route::post('/completions/{completion}/dispute', [CompletionController::class, 'dispute']);
    Route::get('/verify-queue', [CompletionController::class, 'queue']);

    Route::post('/dev/seed', [DevController::class, 'seed']);
});
