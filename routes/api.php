<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CopilotController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\RagSearchController;
use App\Http\Controllers\SceneController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [LoginController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', MeController::class);
    Route::post('/logout', [LoginController::class, 'destroy']);
    Route::get('/game-sessions/active', [GameSessionController::class, 'active']);
    Route::get('/messages', [ChatController::class, 'index']);
    Route::post('/messages', [ChatController::class, 'store']);
    Route::get('/rag/search', RagSearchController::class)->middleware('storyteller');
    Route::post('/copilot/drafts', [CopilotController::class, 'drafts'])->middleware('storyteller');

    Route::middleware('storyteller')->group(function () {
        Route::post('/game-sessions', [GameSessionController::class, 'store']);
        Route::post('/game-sessions/{gameSession}/scenes', [SceneController::class, 'store']);
        Route::patch('/scenes/{scene}/activate', [SceneController::class, 'activate']);
        Route::patch('/scenes/{scene}/close', [SceneController::class, 'close']);
    });
});
