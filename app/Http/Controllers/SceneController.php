<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSceneRequest;
use App\Models\GameSession;
use App\Models\Scene;
use App\Scene\SceneLifecycleService;
use Illuminate\Http\JsonResponse;

class SceneController extends Controller
{
    public function __construct(private SceneLifecycleService $scenes) {}

    public function store(StoreSceneRequest $request, GameSession $gameSession): JsonResponse
    {
        $scene = $this->scenes->create(
            $gameSession,
            $request->validated('title'),
            $request->validated('description'),
            $request->boolean('activate', true),
        );

        return response()->json(['scene' => $this->serialize($scene)], 201);
    }

    public function activate(Scene $scene): JsonResponse
    {
        $scene = $this->scenes->activate($scene);

        return response()->json(['scene' => $this->serialize($scene)]);
    }

    public function close(Scene $scene): JsonResponse
    {
        $scene = $this->scenes->close($scene);

        return response()->json(['scene' => $this->serialize($scene)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Scene $scene): array
    {
        return [
            'id' => $scene->id,
            'game_session_id' => $scene->game_session_id,
            'title' => $scene->title,
            'description' => $scene->description,
            'position' => $scene->position,
            'status' => $scene->status->value,
            'started_at' => $scene->started_at?->toISOString(),
            'ended_at' => $scene->ended_at?->toISOString(),
        ];
    }
}
