<?php

namespace Database\Factories;

use App\Enums\CharacterKnowledgeLevel;
use App\Models\Character;
use App\Models\CharacterRuleKnowledge;
use App\Models\RuleDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterRuleKnowledge>
 */
class CharacterRuleKnowledgeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'rule_document_id' => RuleDocument::factory(),
            'knowledge_level' => CharacterKnowledgeLevel::Known,
            'confidence' => 3,
            'learned_at' => now(),
            'approved_at' => null,
            'approved_by' => null,
        ];
    }
}
