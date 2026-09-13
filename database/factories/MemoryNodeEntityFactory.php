<?php

namespace Database\Factories;

use App\Enums\MemoryEntityRole;
use App\Models\CharacterMemoryNode;
use App\Models\MemoryNodeEntity;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryNodeEntity>
 */
class MemoryNodeEntityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'memory_node_id' => CharacterMemoryNode::factory(),
            'character_id' => fn (array $attributes) => CharacterMemoryNode::query()
                ->findOrFail($attributes['memory_node_id'])
                ->character_id,
            'entity_id' => fn (array $attributes) => WorldEntity::factory()->create([
                'chronicle_id' => CharacterMemoryNode::query()
                    ->findOrFail($attributes['memory_node_id'])
                    ->character
                    ->chronicle_id,
            ])->id,
            'chronicle_id' => fn (array $attributes) => CharacterMemoryNode::query()
                ->findOrFail($attributes['memory_node_id'])
                ->character
                ->chronicle_id,
            'role' => MemoryEntityRole::Subject,
        ];
    }
}
