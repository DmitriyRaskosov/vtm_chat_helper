<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CharacterSheetController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\CopilotController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\SceneContextController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SceneParticipantController;
use App\Http\Controllers\WorldEntityController;
use App\Http\Controllers\WorldRelationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [LoginController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', MeController::class);
    Route::post('/logout', [LoginController::class, 'destroy']);
    Route::get('/game-sessions/active', [GameSessionController::class, 'active']);
    Route::get('/messages', [ChatController::class, 'index']);
    Route::post('/messages', [ChatController::class, 'store']);

    Route::get('/character-sheet/catalog', [CharacterSheetController::class, 'catalog']);

    Route::get('/characters', [CharacterSheetController::class, 'index']);
    Route::get('/characters/{character}', [CharacterSheetController::class, 'show']);

    Route::patch('/characters/{character}', [CharacterSheetController::class, 'update']);
    Route::put('/characters/{character}/disciplines', [CharacterSheetController::class, 'updateDisciplines']);

    Route::put('/characters/{character}/biography', [CharacterSheetController::class, 'updateBiography']);

    Route::post('/copilot/drafts', [CopilotController::class, 'drafts'])->middleware('storyteller');

    Route::middleware('storyteller')->group(function () {
        Route::post('/game-sessions', [GameSessionController::class, 'store']);
        Route::post('/characters', [CharacterSheetController::class, 'store']);

        Route::post('/characters/{character}/archive', [CharacterSheetController::class, 'archive']);
        Route::post('/characters/{character}/restore', [CharacterSheetController::class, 'restore']);
        Route::put('/characters/{character}/place', [CharacterSheetController::class, 'updatePlace']);

        Route::get('/world/entities', [WorldEntityController::class, 'index']);
        Route::post('/world/entities', [WorldEntityController::class, 'store']);
        Route::put('/world/entities/{worldEntity}', [WorldEntityController::class, 'update']);
        Route::post('/world/entities/{worldEntity}/archive', [WorldEntityController::class, 'archive']);
        Route::post('/world/entities/{worldEntity}/restore', [WorldEntityController::class, 'restore']);
        Route::get('/world/entities/{worldEntity}/neighbors', [WorldRelationController::class, 'neighbors']);

        Route::get('/world/relations', [WorldRelationController::class, 'index']);
        Route::post('/world/relations', [WorldRelationController::class, 'store']);
        Route::post('/world/relations/{worldRelation}/end', [WorldRelationController::class, 'end']);

        Route::post('/game-sessions/{gameSession}/scenes', [SceneController::class, 'store']);
        Route::patch('/scenes/{scene}/activate', [SceneController::class, 'activate']);
        Route::patch('/scenes/{scene}/close', [SceneController::class, 'close']);
        Route::get('/scenes/{scene}/context', [SceneContextController::class, 'show']);
        Route::put('/scenes/{scene}/context', [SceneContextController::class, 'update']);
        Route::get('/scenes/{scene}/participants', [SceneParticipantController::class, 'index']);
        Route::post('/scenes/{scene}/participants', [SceneParticipantController::class, 'store']);
        Route::patch('/scenes/{scene}/participants/{character}', [SceneParticipantController::class, 'leave']);
    });
});