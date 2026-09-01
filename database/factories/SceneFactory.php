<?php

namespace Database\Factories;

use App\Enums\SceneStatus;
use App\Models\GameSession;
use App\Models\Scene;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Scene>
 */
class SceneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'game_session_id' => GameSession::factory(),
            'position' => 1,
            'title' => fake()->words(3, true),
            'description' => null,
            'status' => SceneStatus::Draft,
            'started_at' => null,
            'ended_at' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => SceneStatus::Active,
            'started_at' => now(),
            'ended_at' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn () => [
            'status' => SceneStatus::Closed,
            'ended_at' => now(),
        ]);
    }
}
