<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSceneRequest;
use App\Http\Resources\SceneResource;
use App\Models\GameSession;
use App\Models\Scene;
use App\Scene\SceneLifecycleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json([
            'scene' => (new SceneResource($scene))->toArray($request),
        ], 201);
    }

    public function activate(Request $request, Scene $scene): JsonResponse
    {
        $scene = $this->scenes->activate($scene);

        return response()->json([
            'scene' => (new SceneResource($scene))->toArray($request),
        ]);
    }

    public function close(Request $request, Scene $scene): JsonResponse
    {
        $scene = $this->scenes->close($scene);

        return response()->json([
            'scene' => (new SceneResource($scene))->toArray($request),
        ]);
    }
}
