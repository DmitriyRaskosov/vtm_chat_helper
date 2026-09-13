<?php

namespace Database\Factories;

use App\Enums\CharacterStatusEffectType;
use App\Models\Character;
use App\Models\CharacterStatusEffect;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterStatusEffect>
 */
class CharacterStatusEffectFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'effect_type' => CharacterStatusEffectType::Temporary,
            'description' => fake()->sentence(),
            'modifier' => null,
            'active_from' => null,
            'active_until' => null,
            'source_type' => null,
            'source_id' => null,
            'is_active' => true,
        ];
    }
}
