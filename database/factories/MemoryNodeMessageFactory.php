<?php

namespace Database\Factories;

use App\Models\CharacterMemoryNode;
use App\Models\MemoryNodeMessage;
use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemoryNodeMessage>
 */
class MemoryNodeMessageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'memory_node_id' => CharacterMemoryNode::factory(),
            'character_id' => fn (array $attributes) => CharacterMemoryNode::query()
                ->findOrFail($attributes['memory_node_id'])
                ->character_id,
            'message_id' => Message::factory(),
        ];
    }
}
