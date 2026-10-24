<?php

namespace App\Http\Controllers;

use App\Enums\DiceRollMode;
use App\Http\Requests\RollDiceRequest;
use App\Http\Resources\SceneLogResource;
use App\Models\Chronicle;
use App\Models\Scene;
use App\Scene\SceneDiceRollService;
use App\Scene\SceneLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DiceRollController extends Controller
{
    public function __construct(
        private SceneDiceRollService $diceRolls,
        private SceneLogService $sceneLogs,
    ) {}

    public function store(RollDiceRequest $request, Chronicle $chronicle, Scene $scene): JsonResponse
    {
        $validated = $request->validated();
        $characterId = isset($validated['character_id']) ? (int) $validated['character_id'] : null;

        $mode = isset($validated['mode'])
            ? DiceRollMode::from($validated['mode'])
            : DiceRollMode::Successes;

        $rolled = $this->diceRolls->roll(
            $chronicle,
            $scene,
            $request->user(),
            (int) $validated['pool'],
            (int) ($validated['difficulty'] ?? 6),
            (int) $validated['dice_type'],
            $characterId,
            $request->boolean('is_hidden'),
            $mode,
            $validated['comment'] ?? null,
        );

        return response()->json([
            'log' => (new SceneLogResource($rolled['log']))->toArray($request),
            'result' => $rolled['result']->toPayload(),
        ], 201);
    }

    public function index(Request $request, Chronicle $chronicle, Scene $scene): JsonResponse
    {
        $afterId = $request->query('after_id');
        $afterId = $afterId !== null && $afterId !== '' ? (int) $afterId : null;

        $includeHidden = $request->user()->isStorytellerIn($chronicle);
        $logs = $this->sceneLogs->listForScene($scene, $includeHidden, $afterId);

        return response()->json([
            'logs' => $logs
                ->map(fn ($log) => (new SceneLogResource($log))->toArray($request))
                ->values(),
        ]);
    }
}
