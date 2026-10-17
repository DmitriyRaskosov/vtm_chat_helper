<?php

namespace App\Http\Controllers;

use App\Chronicle\CurrentChronicle;
use App\Http\Requests\StoreGameSessionRequest;
use App\Http\Resources\GameSessionResource;
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
        $gameSession = $this->sessions->startNew(
            $this->currentChronicle->id(),
            $request->validated('title'),
            (int) $request->user()->id,
        );

        return response()->json([
            'game_session' => (new GameSessionResource($gameSession))->toArray($request),
        ], 201);
    }
}
