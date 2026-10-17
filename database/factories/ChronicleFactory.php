<?php

namespace Database\Factories;

use App\Enums\ChronicleStatus;
use App\Models\Chronicle;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chronicle>
 */
class ChronicleFactory extends Factory
{
    protected $model = Chronicle::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'setting' => fake()->optional()->word(),
            'status' => ChronicleStatus::Active,
            'created_by' => User::factory(),
        ];
    }
}
