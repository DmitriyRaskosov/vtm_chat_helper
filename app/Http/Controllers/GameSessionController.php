<?php

namespace App\Http\Controllers;

use App\Chronicle\CurrentChronicle;
use App\Http\Requests\SetGameSessionTurnRequest;
use App\Http\Requests\StoreGameSessionRequest;
use App\Http\Resources\GameSessionResource;
use App\Models\GameSession;
use App\Scene\GameSessionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GameSessionController extends Controller
{
    public function __construct(
        private GameSessionService $sessions,
        private CurrentChronicle $currentChronicle,
    ) {}

    public function active(Request $request): JsonResponse
    {
        $this->authorize('view', $this->currentChronicle->get());

        $request->validate([
            'chronicle_id' => ['prohibited'],
        ]);
        $chronicleId = $this->currentChronicle->id();

        $gameSession = $this->sessions->findActiveWithScenes($chronicleId);

        return response()->json([
            'game_session' => $gameSession === null
                ? null
                : (new GameSessionResource($gameSession))->toArray($request),
        ]);
    }

    public function store(StoreGameSessionRequest $request): JsonResponse
    {
        $this->authorize('view', $this->currentChronicle->get());
        $this->authorize('create', GameSession::class);

        $gameSession = $this->sessions->startNew(
            $this->currentChronicle->id(),
            $request->validated('title'),
            (int) $request->user()->id,
        );

        return response()->json([
            'game_session' => (new GameSessionResource($gameSession))->toArray($request),
        ], 201);
    }

    public function setTurn(SetGameSessionTurnRequest $request, GameSession $gameSession): JsonResponse
    {
        $characterId = $request->validated('character_id');
        $characterId = $characterId === null ? null : (int) $characterId;

        $gameSession->loadMissing('chronicle');
        $user = $request->user();

        if ($characterId === null && ! $user->isStorytellerIn($gameSession->chronicle)) {
            $this->authorize('releaseTurn', $gameSession);
            $updated = $this->sessions->releaseActiveCharacterForPlayer($gameSession, $user);
        } else {
            $this->authorize('setTurn', $gameSession);
            $updated = $this->sessions->setActiveCharacter($gameSession, $characterId);
        }

        return response()->json([
            'game_session' => (new GameSessionResource($updated->load('scenes')))->toArray($request),
        ]);
    }
}
