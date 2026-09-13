<?php

namespace Database\Factories;

use App\Models\CharacterMemoryNode;
use App\Models\LoreEntry;
use App\Models\MemoryNodeLoreEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryNodeLoreEntry>
 */
class MemoryNodeLoreEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'memory_node_id' => CharacterMemoryNode::factory(),
            'character_id' => fn (array $attributes) => CharacterMemoryNode::query()
                ->findOrFail($attributes['memory_node_id'])
                ->character_id,
            'lore_entry_id' => fn (array $attributes) => LoreEntry::factory()->create([
                'chronicle_id' => CharacterMemoryNode::query()
                    ->findOrFail($attributes['memory_node_id'])
                    ->character
                    ->chronicle_id,
            ])->id,
            'chronicle_id' => fn (array $attributes) => CharacterMemoryNode::query()
                ->findOrFail($attributes['memory_node_id'])
                ->character
                ->chronicle_id,
        ];
    }
}
