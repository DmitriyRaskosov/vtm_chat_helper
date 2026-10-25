<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateSceneEventRequest;
use App\Models\Chronicle;
use App\Models\Scene;
use App\Scene\EventGeneratorService;
use Illuminate\Http\JsonResponse;

class EventGeneratorController extends Controller
{
    public function __construct(private EventGeneratorService $events) {}

    public function store(GenerateSceneEventRequest $request, Chronicle $chronicle, Scene $scene): JsonResponse
    {
        $validated = $request->validated();

        $text = $this->events->generate(
            $validated['action_description'],
            (int) $validated['attack_successes'],
            $request->boolean('attack_botch'),
            (int) $validated['defense_successes'],
            $request->boolean('defense_botch'),
        );

        return response()->json([
            'text' => $text,
        ]);
    }
}
