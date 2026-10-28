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
    protected $model = Scene::class;

    public function definition(): array
    {
        return [
            'game_session_id' => GameSession::factory()->active(),
            'position' => 1,
            'title' => fake()->sentence(3),
            'description' => fake()->optional()->paragraph(),
            'status' => SceneStatus::Draft,
            'started_at' => null,
            'ended_at' => null,
        ];
    }

    public function forGameSession(GameSession $gameSession): static
    {
        $nextPosition = (int) $gameSession->scenes()->max('position') + 1;

        return $this->state(fn (array $attributes) => [
            'game_session_id' => $gameSession->id,
            'position' => $nextPosition > 0 ? $nextPosition : 1,
        ]);
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SceneStatus::Active,
            'started_at' => now(),
            'ended_at' => null,
        ]);
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => SceneStatus::Closed,
            'ended_at' => now(),
        ]);
    }
}
