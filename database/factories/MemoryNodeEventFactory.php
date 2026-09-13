<?php

namespace Database\Factories;

use App\Enums\WorldEntityType;
use App\Models\CharacterMemoryNode;
use App\Models\MemoryNodeEvent;
use App\Models\WorldEntity;
use App\Models\WorldEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryNodeEvent>
 */
class MemoryNodeEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'memory_node_id' => CharacterMemoryNode::factory(),
            'character_id' => fn (array $attributes) => CharacterMemoryNode::query()
                ->findOrFail($attributes['memory_node_id'])
                ->character_id,
            'event_id' => function (array $attributes) {
                $chronicleId = CharacterMemoryNode::query()
                    ->findOrFail($attributes['memory_node_id'])
                    ->character
                    ->chronicle_id;

                $identity = WorldEntity::factory()->create([
                    'chronicle_id' => $chronicleId,
                    'entity_type' => WorldEntityType::Event,
                ]);

                return WorldEvent::factory()->create([
                    'id' => $identity->id,
                    'chronicle_id' => $chronicleId,
                ])->id;
            },
            'chronicle_id' => fn (array $attributes) => CharacterMemoryNode::query()
                ->findOrFail($attributes['memory_node_id'])
                ->character
                ->chronicle_id,
        ];
    }
}
