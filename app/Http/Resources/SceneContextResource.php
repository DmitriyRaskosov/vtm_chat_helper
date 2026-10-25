<?php

namespace App\Http\Resources;

use App\Models\Scene;
use App\Models\SceneContext;
use App\Scene\SceneContextService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Scene
 */
class SceneContextResource extends JsonResource
{
    public function __construct(
        Scene $scene,
        private readonly ?SceneContext $context,
        private readonly SceneContextService $contexts,
    ) {
        parent::__construct($scene);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $scene = $this->resource;
        $context = $this->context;
        $locationId = $context?->location_entity_id;

        return [
            'scene_id' => (int) $scene->id,
            'location_entity_id' => $locationId,
            'location_name' => $this->contexts->locationCanonicalName($context),
            'atmosphere' => $context?->atmosphere,
            'situation' => $context?->situation,
            'storyteller_notes' => $context?->storyteller_notes,
            'environment_details' => $context?->environment_details,
            'revision' => $context === null ? 0 : (int) $context->revision,
            'frozen_revision' => $context?->frozen_revision,
            'updated_by' => $context?->updated_by,
            'updated_at' => $context?->updated_at?->toISOString(),
        ];
    }
}
