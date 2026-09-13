<?php

namespace Database\Factories;

use App\Models\CharacterMemoryNode;
use App\Models\MemoryNodeScene;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryNodeScene>
 */
class MemoryNodeSceneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'memory_node_id' => CharacterMemoryNode::factory(),
            'character_id' => fn (array $attributes) => CharacterMemoryNode::query()
                ->findOrFail($attributes['memory_node_id'])
                ->character_id,
            'scene_id' => Scene::factory(),
        ];
    }
}
