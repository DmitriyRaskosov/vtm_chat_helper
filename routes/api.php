<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CopilotController;
use App\Http\Controllers\RagSearchController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [LoginController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', MeController::class);
    Route::post('/logout', [LoginController::class, 'destroy']);
    Route::get('/messages', [ChatController::class, 'index']);
    Route::post('/messages', [ChatController::class, 'store']);
    Route::get('/rag/search', RagSearchController::class)->middleware('storyteller');
    Route::post('/copilot/drafts', [CopilotController::class, 'drafts'])->middleware('storyteller');
});
