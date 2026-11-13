<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\MeController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\CharacterAvatarController;
use App\Http\Controllers\CharacterItemController;
use App\Http\Controllers\CharacterSheetController;
use App\Http\Controllers\CharacterSheetImageController;
use App\Http\Controllers\CharacterStatsController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ChronicleController;
use App\Http\Controllers\ChronicleInvitationController;
use App\Http\Controllers\ChronicleLocationController;
use App\Http\Controllers\ChronicleLocationImageController;
use App\Http\Controllers\CombatController;
use App\Http\Controllers\CopilotController;
use App\Http\Controllers\DiaryEntryController;
use App\Http\Controllers\DiceMacroController;
use App\Http\Controllers\DiceRollController;
use App\Http\Controllers\EventGeneratorController;
use App\Http\Controllers\GameSessionController;
use App\Http\Controllers\LibraryCharacterController;
use App\Http\Controllers\LocationNodeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PromptController;
use App\Http\Controllers\SceneContextController;
use App\Http\Controllers\SceneContextImageController;
use App\Http\Controllers\SceneController;
use App\Http\Controllers\SceneParticipantController;
use App\Http\Controllers\SceneTypingController;
use App\Http\Controllers\UserNoteController;
use App\Models\CombatEncounter;
use App\Models\LibraryCharacter;
use App\Models\PromptOverride;
use Illuminate\Support\Facades\Route;

Route::post('/register', [RegisterController::class, 'store']);
Route::post('/login', [LoginController::class, 'store']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', MeController::class);
    Route::post('/logout', [LoginController::class, 'destroy']);
    Route::get('/chronicles', [ChronicleController::class, 'index']);
    Route::post('/chronicles', [ChronicleController::class, 'store']);
    Route::get('/chronicles/{chronicle}', [ChronicleController::class, 'show']);
    Route::get('/chronicles/{chronicle}/invitations', [ChronicleInvitationController::class, 'index']);
    Route::post('/chronicles/{chronicle}/invitations', [ChronicleInvitationController::class, 'store']);
    Route::delete('/chronicles/{chronicle}/invitations/{invitation}', [ChronicleInvitationController::class, 'destroy']);
    Route::post('/invitations/{token}/accept', [ChronicleInvitationController::class, 'accept']);

    Route::prefix('/chronicles/{chronicle}')->scopeBindings()->group(function () {
        Route::get('/game-sessions/active', [GameSessionController::class, 'active']);
        Route::get('/messages', [ChatController::class, 'index']);
        Route::post('/messages', [ChatController::class, 'store']);
        Route::get('/characters', [CharacterSheetController::class, 'index']);
        Route::post('/characters', [CharacterSheetController::class, 'store']);
        Route::post('/characters/import', [LibraryCharacterController::class, 'import'])
            ->can('view', 'chronicle');
        Route::get('/characters/{character}/note', [UserNoteController::class, 'show']);
        Route::put('/characters/{character}/note', [UserNoteController::class, 'update']);
        Route::post('/game-sessions', [GameSessionController::class, 'store']);
        Route::patch('/time', [ChronicleController::class, 'updateTime'])
            ->can('update', 'chronicle');
        Route::post('/copilot/drafts', [CopilotController::class, 'drafts']);
        Route::post('/scenes/{scene}/dice-rolls', [DiceRollController::class, 'store'])
            ->can('view', 'scene');
        Route::get('/scenes/{scene}/logs', [DiceRollController::class, 'index'])
            ->can('view', 'scene');
        Route::post('/scenes/{scene}/events/generate', [EventGeneratorController::class, 'store'])
            ->can('update', 'scene');
        Route::get('/locations', [ChronicleLocationController::class, 'index'])
            ->can('update', 'chronicle');
        Route::post('/locations', [ChronicleLocationController::class, 'store'])
            ->can('update', 'chronicle');
        Route::post('/scenes/{scene}/context/apply-template', [SceneContextController::class, 'applyTemplate'])
            ->can('update', 'scene');
    });

    Route::patch('/locations/{chronicleLocation}', [ChronicleLocationController::class, 'update'])
        ->can('update', 'chronicleLocation');
    Route::get('/locations/{chronicleLocation}/location-nodes', [LocationNodeController::class, 'index'])
        ->can('update', 'chronicleLocation');
    Route::post('/locations/{chronicleLocation}/location-nodes/parse', [LocationNodeController::class, 'parse'])
        ->can('update', 'chronicleLocation');
    Route::patch('/locations/{chronicleLocation}/location-nodes/{locationNode}', [LocationNodeController::class, 'update'])
        ->scopeBindings()
        ->can('update', 'chronicleLocation');
    Route::delete('/locations/{chronicleLocation}/location-nodes/{locationNode}', [LocationNodeController::class, 'destroy'])
        ->scopeBindings()
        ->can('update', 'chronicleLocation');
    Route::delete('/locations/{chronicleLocation}', [ChronicleLocationController::class, 'destroy'])
        ->can('delete', 'chronicleLocation');
    Route::get('/locations/{chronicleLocation}/image', [ChronicleLocationImageController::class, 'show'])
        ->can('view', 'chronicleLocation');
    Route::post('/locations/{chronicleLocation}/image', [ChronicleLocationImageController::class, 'store'])
        ->can('update', 'chronicleLocation');

    Route::patch('/messages/{message}', [ChatController::class, 'update']);
    Route::patch('/messages/{message}/ooc', [ChatController::class, 'toggleOoc']);
    Route::get('/workshop/prompts', [PromptController::class, 'index'])
        ->can('viewAny', PromptOverride::class);
    Route::put('/workshop/prompts/{key}', [PromptController::class, 'update'])
        ->where('key', '.+')
        ->can('update', PromptOverride::class);
    Route::delete('/workshop/prompts/{key}', [PromptController::class, 'destroy'])
        ->where('key', '.+')
        ->can('delete', PromptOverride::class);

    Route::get('/dice-macros', [DiceMacroController::class, 'index']);
    Route::post('/dice-macros', [DiceMacroController::class, 'store']);
    Route::patch('/dice-macros/{macro}', [DiceMacroController::class, 'update'])
        ->can('update', 'macro');
    Route::delete('/dice-macros/{macro}', [DiceMacroController::class, 'destroy'])
        ->can('delete', 'macro');
    Route::delete('/messages/{message}', [ChatController::class, 'destroy']);
    Route::post('/messages/{message}/restore', [ChatController::class, 'restore'])
        ->withTrashed();

    Route::get('/character-sheet/catalog', [CharacterSheetController::class, 'catalog']);

    Route::get('/profile/characters', [ProfileController::class, 'index']);

    Route::get('/library-characters', [LibraryCharacterController::class, 'index'])
        ->can('viewAny', LibraryCharacter::class);
    Route::post('/library-characters', [LibraryCharacterController::class, 'store'])
        ->can('create', LibraryCharacter::class);
    Route::delete('/library-characters/{libraryCharacter}', [LibraryCharacterController::class, 'destroy'])
        ->can('delete', 'libraryCharacter');

    Route::get('/characters/{character}', [CharacterSheetController::class, 'show']);
    Route::get('/characters/{character}/sheet-image', [CharacterSheetImageController::class, 'show']);
    Route::post('/characters/{character}/sheet-image', [CharacterSheetImageController::class, 'store'])
        ->can('manageProfile', 'character');
    Route::post('/characters/{character}/avatar', [CharacterAvatarController::class, 'store'])
        ->can('manageProfile', 'character');

    Route::patch('/characters/{character}', [CharacterSheetController::class, 'update']);
    Route::put('/characters/{character}/disciplines', [CharacterSheetController::class, 'updateDisciplines']);

    Route::put('/characters/{character}/biography', [CharacterSheetController::class, 'updateBiography']);

    Route::post('/characters/{character}/archive', [CharacterSheetController::class, 'archive'])
        ->can('archive', 'character');
    Route::post('/characters/{character}/restore', [CharacterSheetController::class, 'restore'])
        ->can('restore', 'character');
    Route::delete('/characters/{character}', [CharacterSheetController::class, 'destroy'])
        ->can('delete', 'character');
    Route::put('/characters/{character}/place', [CharacterSheetController::class, 'updatePlace'])
        ->can('updatePlace', 'character');
    Route::patch('/characters/{character}/stats', [CharacterStatsController::class, 'update']);
    Route::get('/characters/{character}/items', [CharacterItemController::class, 'index']);
    Route::post('/characters/{character}/items', [CharacterItemController::class, 'store']);
    Route::patch('/characters/{character}/items/{item}', [CharacterItemController::class, 'update'])
        ->scopeBindings();
    Route::delete('/characters/{character}/items/{item}', [CharacterItemController::class, 'destroy'])
        ->scopeBindings();

    Route::get('/scenes/{scene}/context', [SceneContextController::class, 'show'])
        ->can('view', 'scene');
    Route::get('/scenes/{scene}/image', [SceneContextImageController::class, 'show'])
        ->can('view', 'scene');
    Route::post('/scenes/{scene}/image', [SceneContextImageController::class, 'store'])
        ->can('update', 'scene');
    Route::get('/scenes/{scene}/participants', [SceneParticipantController::class, 'index'])
        ->can('view', 'scene');
    Route::post('/scenes/{scene}/typing', [SceneTypingController::class, 'store'])
        ->can('view', 'scene');

    Route::post('/game-sessions/{gameSession}/scenes', [SceneController::class, 'store']);
    Route::patch('/game-sessions/{gameSession}/turn', [GameSessionController::class, 'setTurn']);

    Route::patch('/scenes/{scene}/activate', [SceneController::class, 'activate'])
        ->can('activate', 'scene');
    Route::patch('/scenes/{scene}/close', [SceneController::class, 'close'])
        ->can('close', 'scene');
    Route::post('/scenes/{scene}/combat', [CombatController::class, 'store'])
        ->can('create', [CombatEncounter::class, 'scene']);
    Route::get('/scenes/{scene}/combat', [CombatController::class, 'show'])
        ->can('view', 'scene');
    Route::post('/combat/{combat}/participants', [CombatController::class, 'addParticipant'])
        ->can('update', 'combat');
    Route::patch('/combat/{combat}/participants/{participant}/active', [CombatController::class, 'setActive'])
        ->can('update', 'combat');
    Route::post('/combat/{combat}/participants/{participant}/reveal', [CombatController::class, 'reveal'])
        ->can('update', 'combat');
    Route::post('/combat/{combat}/participants/{participant}/initiative', [CombatController::class, 'setInitiative'])
        ->can('update', 'combat');
    Route::post('/combat/{combat}/roll-initiative', [CombatController::class, 'rollInitiative'])
        ->can('update', 'combat');
    Route::post('/combat/{combat}/next-turn', [CombatController::class, 'nextTurn'])
        ->can('update', 'combat');
    Route::post('/combat/{combat}/finish', [CombatController::class, 'finish'])
        ->can('update', 'combat');
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
