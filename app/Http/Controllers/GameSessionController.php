<?php

namespace App\Http\Controllers;

use App\Chronicle\CurrentChronicle;
use App\Enums\SceneStatus;
use App\Http\Requests\StoreGameSessionRequest;
use App\Models\GameSession;
use App\Models\Scene;
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
            'game_session' => $gameSession === null ? null : $this->serialize($gameSession),
        ]);
    }

    public function store(StoreGameSessionRequest $request): JsonResponse
    {
        $gameSession = $this->sessions->startNew(
            $this->currentChronicle->id(),
            $request->validated('title'),
            (int) $request->user()->id,
        );

        return response()->json(['game_session' => $this->serialize($gameSession)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(GameSession $gameSession): array
    {
        return [
            'id' => $gameSession->id,
            'chronicle_id' => $gameSession->chronicle_id,
            'title' => $gameSession->title,
            'status' => $gameSession->status->value,
            'active_scene_id' => $gameSession->scenes
                ->firstWhere('status', SceneStatus::Active)?->id,
            'scenes' => $gameSession->scenes
                ->map(fn (Scene $scene): array => [
                    'id' => $scene->id,
                    'title' => $scene->title,
                    'description' => $scene->description,
                    'position' => $scene->position,
                    'status' => $scene->status->value,
                    'started_at' => $scene->started_at?->toISOString(),
                    'ended_at' => $scene->ended_at?->toISOString(),
                ])
                ->values(),
        ];
    }
}
