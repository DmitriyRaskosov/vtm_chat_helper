<?php

namespace Database\Factories;

use App\Enums\CharacterMemoryNodeType;
use App\Enums\CharacterMemoryStatus;
use App\Models\Character;
use App\Models\CharacterMemoryNode;
use App\Rag\StubEmbeddingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterMemoryNode>
 */
class CharacterMemoryNodeFactory extends Factory
{
    public function definition(): array
    {
        $text = 'Виктория видела князя в опере.';

        return [
            'character_id' => Character::factory(),
            'node_text' => $text,
            'node_type' => CharacterMemoryNodeType::Event,
            'importance' => 2,
            'emotional_valence' => 0,
            'arousal' => 1,
            'confidence' => 3,
            'is_false_belief' => false,
            'recall_count' => 0,
            'last_recalled_at' => null,
            'last_recall_score' => null,
            'status' => CharacterMemoryStatus::Approved,
            'aliases' => [],
            'provenance' => [],
            'approved_at' => now(),
            'approved_by' => null,
            'created_by' => null,
            'embedding' => (new StubEmbeddingProvider)->embed($text),
        ];
    }
}
