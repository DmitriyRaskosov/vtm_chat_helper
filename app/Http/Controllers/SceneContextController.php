<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSceneContextRequest;
use App\Http\Resources\SceneContextResource;
use App\Models\Scene;
use App\Scene\SceneContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SceneContextController extends Controller
{
    public function __construct(private SceneContextService $contexts) {}

    public function show(Request $request, Scene $scene): JsonResponse
    {
        return response()->json([
            'context' => (new SceneContextResource($scene, $this->contexts->current($scene), $this->contexts))
                ->toArray($request),
        ]);
    }

    public function update(UpdateSceneContextRequest $request, Scene $scene): JsonResponse
    {
        $fields = $request->safe()->only([
            'location_entity_id',
            'atmosphere',
            'situation',
            'storyteller_notes',
            'environment_details',
        ]);

        $context = $this->contexts->apply(
            $scene,
            $fields,
            (int) $request->validated('expected_revision'),
            $request->user(),
        );

        return response()->json([
            'context' => (new SceneContextResource($scene, $context, $this->contexts))->toArray($request),
        ]);
    }
}
