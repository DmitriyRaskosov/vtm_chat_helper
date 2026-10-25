<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateSceneEventRequest;
use App\Models\Chronicle;
use App\Models\Scene;
use App\Scene\EventGeneratorService;
use App\Scene\Exceptions\EventGenerationFailedException;
use Illuminate\Http\JsonResponse;

class EventGeneratorController extends Controller
{
    public function __construct(private EventGeneratorService $events) {}

    public function store(GenerateSceneEventRequest $request, Chronicle $chronicle, Scene $scene): JsonResponse
    {
        $validated = $request->validated();

        try {
            $description = $this->events->generate(
                $scene,
                (string) $validated['action_description'],
                (int) $validated['attack_successes'],
                $request->boolean('attack_botch'),
                $request->boolean('attack_willpower'),
                (int) $validated['defense_successes'],
                $request->boolean('defense_botch'),
                $request->boolean('defense_willpower'),
            );
        } catch (EventGenerationFailedException $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage(),
            ], 503);
        }

        return response()->json([
            'description' => $description,
        ]);
    }
}
