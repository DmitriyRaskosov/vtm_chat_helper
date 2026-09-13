<?php

namespace Database\Factories;

use App\Models\Discipline;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discipline>
 */
class DisciplineFactory extends Factory
{
    public function definition(): array
    {
        return [
            'ruleset' => 'v20',
            'key' => fake()->unique()->lexify('disc_????'),
            'display_name' => fake()->word(),
        ];
    }
}
