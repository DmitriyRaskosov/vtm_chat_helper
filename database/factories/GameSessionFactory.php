<?php

namespace Database\Factories;

use App\Enums\GameSessionStatus;
use App\Models\GameSession;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameSession>
 */
class GameSessionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'status' => GameSessionStatus::Archived,
            'created_by' => null,
            'activated_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => GameSessionStatus::Active,
            'activated_at' => now(),
        ]);
    }
}
