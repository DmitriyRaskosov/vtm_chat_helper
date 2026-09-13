<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterDiscipline;
use App\Models\Discipline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterDiscipline>
 */
class CharacterDisciplineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'discipline_id' => Discipline::factory(),
            'level' => 1,
        ];
    }
}
