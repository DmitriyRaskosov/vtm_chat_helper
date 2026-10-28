<?php

namespace App\Http\Controllers;

use App\Chronicle\ChronicleLocationService;
use App\Http\Requests\ApplyLocationTemplateRequest;
use App\Http\Requests\UpdateSceneContextRequest;
use App\Http\Resources\SceneContextResource;
use App\Models\Chronicle;
use App\Models\ChronicleLocation;
use App\Models\Scene;
use App\Scene\SceneContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SceneContextController extends Controller
{
    public function __construct(
        private SceneContextService $contexts,
        private ChronicleLocationService $locations,
    ) {}

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

    public function applyTemplate(
        ApplyLocationTemplateRequest $request,
        Chronicle $chronicle,
        Scene $scene,
    ): JsonResponse {
        $this->authorize('update', $scene);

        $location = ChronicleLocation::query()
            ->where('chronicle_id', $chronicle->id)
            ->whereKey((int) $request->validated('location_id'))
            ->firstOrFail();

        $context = $this->locations->applyToScene(
            $scene,
            $location,
            (int) $request->validated('expected_revision'),
            $request->user(),
        );

        return response()->json([
            'context' => (new SceneContextResource($scene, $context, $this->contexts))->toArray($request),
        ]);
    }
}
