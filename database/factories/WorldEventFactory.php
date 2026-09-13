<?php

namespace Database\Factories;

use App\Enums\WorldEntityType;
use App\Enums\WorldEventStatus;
use App\Enums\WorldEventType;
use App\Enums\WorldEventVisibility;
use App\Models\WorldEntity;
use App\Models\WorldEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldEvent>
 */
class WorldEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => WorldEntity::factory()->state(['entity_type' => WorldEntityType::Event]),
            'chronicle_id' => fn (array $attributes) => WorldEntity::query()
                ->findOrFail($attributes['id'])
                ->chronicle_id,
            'entity_type' => WorldEntityType::Event,
            'scene_id' => null,
            'title' => 'A court incident',
            'description' => null,
            'event_type' => WorldEventType::Other,
            'status' => WorldEventStatus::Proposed,
            'importance' => 1,
            'visibility' => WorldEventVisibility::Public,
            'approved_at' => null,
            'approved_by' => null,
        ];
    }
}
