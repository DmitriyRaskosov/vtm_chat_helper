<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSceneContextRequest;
use App\Models\Scene;
use App\Models\SceneContext;
use App\Models\WorldEntity;
use App\Scene\SceneContextRevisionException;
use App\Scene\SceneContextService;
use App\Scene\SceneFrozenException;
use App\World\MixedChronicleException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

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

        try {
            $context = $this->contexts->apply(
                $scene,
                $fields,
                (int) $request->validated('expected_revision'),
                $request->user(),
            );
        } catch (SceneContextRevisionException|SceneFrozenException|MixedChronicleException $e) {
            abort(409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['context' => $this->serialize($scene, $context)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Scene $scene, ?SceneContext $context): array
    {
        $locationId = $context?->location_entity_id;
        $locationName = $locationId === null
            ? null
            : WorldEntity::query()->whereKey($locationId)->value('canonical_name');

        return [
            'scene_id' => (int) $scene->id,
            'location_entity_id' => $locationId,
            'location_name' => is_string($locationName) && $locationName !== '' ? $locationName : null,
            'atmosphere' => $context?->atmosphere,
            'situation' => $context?->situation,
            'storyteller_notes' => $context?->storyteller_notes,
            'revision' => (int) ($context?->revision ?? 0),
            'frozen_revision' => $context?->frozen_revision,
            'updated_by' => $context?->updated_by,
            'updated_at' => $context?->updated_at?->toISOString(),
        ];
    }
}
