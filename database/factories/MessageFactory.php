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
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scene_id' => fn () => Scene::query()->active()->value('id')
                ?? Scene::factory()->active()->create()->id,
            'body' => fake()->sentence(),
        ];
    }
}
