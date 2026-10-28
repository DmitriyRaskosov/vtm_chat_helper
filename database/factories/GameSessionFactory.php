<?php

namespace Database\Factories;

use App\Enums\GameSessionStatus;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameSession>
 */
class GameSessionFactory extends Factory
{
    protected $model = GameSession::class;

    public function definition(): array
    {
        return [
            'chronicle_id' => Chronicle::factory(),
            'title' => fake()->sentence(3),
            'status' => GameSessionStatus::Archived,
            'created_by' => User::factory(),
            'activated_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameSessionStatus::Active,
            'activated_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GameSessionStatus::Archived,
            'activated_at' => null,
        ]);
    }
}
