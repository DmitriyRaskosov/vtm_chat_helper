<?php

namespace Database\Factories;

use App\Enums\CharacterMemoryEdgeType;
use App\Models\CharacterMemoryEdge;
use App\Models\CharacterMemoryNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterMemoryEdge>
 */
class CharacterMemoryEdgeFactory extends Factory
{
    public function definition(): array
    {
        $source = CharacterMemoryNode::factory();

        return [
            'source_node_id' => $source,
            'character_id' => fn (array $attributes) => CharacterMemoryNode::query()
                ->findOrFail($attributes['source_node_id'])
                ->character_id,
            'target_node_id' => fn (array $attributes) => CharacterMemoryNode::factory()->create([
                'character_id' => CharacterMemoryNode::query()->findOrFail($attributes['source_node_id'])->character_id,
            ])->id,
            'relation_type' => CharacterMemoryEdgeType::CausedRecall,
            'authored_weight' => 0.8,
            'bidirectional' => false,
            'traversal_count' => 0,
            'last_traversed_at' => null,
            'provenance' => [],
            'approved_at' => null,
            'approved_by' => null,
        ];
    }
}
