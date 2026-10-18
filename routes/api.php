<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CharacterSheetController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChronicleController;
use App\Http\Controllers\ChronicleInvitationController;
use App\Http\Controllers\CopilotController;
use App\Http\Controllers\DiaryEntryController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\SceneContextController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SceneParticipantController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [LoginController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', MeController::class);
    Route::post('/logout', [LoginController::class, 'destroy']);
    Route::get('/chronicles', [ChronicleController::class, 'index']);
    Route::post('/chronicles', [ChronicleController::class, 'store']);
    Route::get('/chronicles/{chronicle}', [ChronicleController::class, 'show']);
    Route::post('/chronicles/{chronicle}/invitations', [ChronicleInvitationController::class, 'store']);
    Route::delete('/chronicles/{chronicle}/invitations/{invitation}', [ChronicleInvitationController::class, 'destroy']);
    Route::post('/invitations/{token}/accept', [ChronicleInvitationController::class, 'accept']);

    Route::prefix('/chronicles/{chronicle}')->scopeBindings()->group(function () {
        Route::get('/game-sessions/active', [GameSessionController::class, 'active']);
        Route::get('/messages', [ChatController::class, 'index']);
        Route::post('/messages', [ChatController::class, 'store']);
        Route::get('/characters', [CharacterSheetController::class, 'index']);
        Route::post('/characters', [CharacterSheetController::class, 'store']);
        Route::post('/game-sessions', [GameSessionController::class, 'store']);
        Route::post('/copilot/drafts', [CopilotController::class, 'drafts']);
    });

    Route::patch('/messages/{message}/ooc', [ChatController::class, 'toggleOoc']);
    Route::delete('/messages/{message}', [ChatController::class, 'destroy']);
    Route::post('/messages/{message}/restore', [ChatController::class, 'restore'])
        ->withTrashed();

    Route::get('/character-sheet/catalog', [CharacterSheetController::class, 'catalog']);

    Route::get('/characters/{character}', [CharacterSheetController::class, 'show']);

    Route::patch('/characters/{character}', [CharacterSheetController::class, 'update']);
    Route::put('/characters/{character}/disciplines', [CharacterSheetController::class, 'updateDisciplines']);

    Route::put('/characters/{character}/biography', [CharacterSheetController::class, 'updateBiography']);
    Route::put('/characters/{character}/traits', [CharacterSheetController::class, 'updateTraits']);

    Route::post('/characters/{character}/archive', [CharacterSheetController::class, 'archive'])
        ->can('archive', 'character');
    Route::post('/characters/{character}/restore', [CharacterSheetController::class, 'restore'])
        ->can('restore', 'character');
    Route::put('/characters/{character}/place', [CharacterSheetController::class, 'updatePlace'])
        ->can('updatePlace', 'character');

    Route::get('/scenes/{scene}/context', [SceneContextController::class, 'show'])
        ->can('view', 'scene');
    Route::get('/scenes/{scene}/participants', [SceneParticipantController::class, 'index'])
        ->can('view', 'scene');

    Route::post('/game-sessions/{gameSession}/scenes', [SceneController::class, 'store']);

    Route::patch('/scenes/{scene}/activate', [SceneController::class, 'activate'])
        ->can('activate', 'scene');
    Route::patch('/scenes/{scene}/close', [SceneController::class, 'close'])
        ->can('close', 'scene');
    Route::put('/scenes/{scene}/context', [SceneContextController::class, 'update'])
        ->can('update', 'scene');
    Route::post('/scenes/{scene}/participants', [SceneParticipantController::class, 'store'])
        ->can('addParticipant', 'scene');
    Route::patch('/scenes/{scene}/participants/{character}', [SceneParticipantController::class, 'leave'])
        ->can('removeParticipant', 'scene');

    Route::get('/characters/{character}/diary', [DiaryEntryController::class, 'index'])
        ->can('viewDiary', 'character');
    Route::post('/characters/{character}/diary/preview', [DiaryEntryController::class, 'preview'])
        ->can('previewDiary', 'character');
    Route::get('/diary-entries/{diaryEntry}', [DiaryEntryController::class, 'show'])
        ->can('view', 'diaryEntry');
    Route::patch('/diary-entries/{diaryEntry}', [DiaryEntryController::class, 'update'])
        ->can('update', 'diaryEntry');
    Route::delete('/diary-entries/{diaryEntry}', [DiaryEntryController::class, 'destroy'])
        ->can('delete', 'diaryEntry');
    Route::post('/diary-entries/{diaryEntry}/regenerate', [DiaryEntryController::class, 'regenerate'])
        ->can('regenerate', 'diaryEntry');
});
