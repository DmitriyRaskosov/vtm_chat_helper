<?php

namespace Database\Factories;

use App\Models\CharacterStat;
use App\Models\CharacterStatSpecialization;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterStatSpecialization>
 */
class CharacterStatSpecializationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_stat_id' => CharacterStat::factory(),
            'name' => fake()->unique()->word(),
            'description' => null,
            'is_active' => true,
        ];
    }
}
