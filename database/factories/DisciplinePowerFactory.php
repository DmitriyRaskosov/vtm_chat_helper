<?php

namespace Database\Factories;

use App\Models\Discipline;
use App\Models\DisciplinePower;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DisciplinePower>
 */
class DisciplinePowerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'discipline_id' => Discipline::factory(),
            'key' => fake()->unique()->lexify('pow_????'),
            'display_name' => fake()->word(),
            'required_level' => 1,
            'rule_key' => null,
        ];
    }
}
