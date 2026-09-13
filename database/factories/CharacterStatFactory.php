<?php

namespace Database\Factories;

use App\Enums\CharacterStatCategory;
use App\Models\Character;
use App\Models\CharacterStat;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterStat>
 */
class CharacterStatFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'category' => CharacterStatCategory::Attribute,
            'stat_key' => fake()->unique()->lexify('stat_????'),
            'display_name' => fake()->word(),
            'value' => 1,
            'maximum' => 5,
            'sort_order' => 0,
            'metadata' => null,
        ];
    }
}
