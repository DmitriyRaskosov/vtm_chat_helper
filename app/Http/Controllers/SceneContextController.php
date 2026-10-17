<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSceneContextRequest;
use App\Models\Scene;
use App\Models\SceneContext;
use App\Scene\SceneContextService;
use Illuminate\Http\JsonResponse;

class SceneContextController extends Controller
{
    public function __construct(private SceneContextService $contexts) {}

    public function show(Scene $scene): JsonResponse
    {
        return response()->json([
            'context' => $this->serialize($scene, $this->contexts->current($scene)),
        ]);
    }

    public function update(UpdateSceneContextRequest $request, Scene $scene): JsonResponse
    {
        $fields = $request->safe()->only([
            'location_entity_id',
            'atmosphere',
            'situation',
            'storyteller_notes',
        ]);

        $context = $this->contexts->apply(
            $scene,
            $fields,
            (int) $request->validated('expected_revision'),
            $request->user(),
        );

        return response()->json(['context' => $this->serialize($scene, $context)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Scene $scene, ?SceneContext $context): array
    {
        $locationId = $context?->location_entity_id;

        return [
            'scene_id' => (int) $scene->id,
            'location_entity_id' => $locationId,
            'location_name' => $this->contexts->locationCanonicalName($context),
            'atmosphere' => $context?->atmosphere,
            'situation' => $context?->situation,
            'storyteller_notes' => $context?->storyteller_notes,
            'revision' => $context === null ? 0 : (int) $context->revision,
            'frozen_revision' => $context?->frozen_revision,
            'updated_by' => $context?->updated_by,
            'updated_at' => $context?->updated_at?->toISOString(),
        ];
    }
}
