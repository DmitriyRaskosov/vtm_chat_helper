<?php

namespace Database\Factories;

use App\Enums\CharacterKnowledgeLevel;
use App\Models\Character;
use App\Models\CharacterLoreKnowledge;
use App\Models\LoreEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterLoreKnowledge>
 */
class CharacterLoreKnowledgeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'lore_entry_id' => fn (array $attributes) => LoreEntry::factory()->create([
                'chronicle_id' => Character::query()->findOrFail($attributes['character_id'])->chronicle_id,
            ])->id,
            'chronicle_id' => fn (array $attributes) => Character::query()
                ->findOrFail($attributes['character_id'])
                ->chronicle_id,
            'knowledge_level' => CharacterKnowledgeLevel::Known,
            'confidence' => 3,
            'learned_at' => now(),
            'source_world_event_id' => null,
            'approved_at' => null,
            'approved_by' => null,
        ];
    }
}
