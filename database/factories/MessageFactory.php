<?php

namespace Database\Factories;

use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scene_id' => Scene::factory()->active(),
            'body' => fake()->paragraph(),
            'npc_name' => null,
            'author_character_id' => null,
            'copilot_request_id' => null,
            'is_ooc' => false,
        ];
    }

    public function ooc(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_ooc' => true,
        ]);
    }
}
