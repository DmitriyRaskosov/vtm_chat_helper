<?php

namespace Database\Factories;

use App\Models\Scene;
use App\Models\WorldEvent;
use App\Models\WorldEventSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldEventSource>
 */
class WorldEventSourceFactory extends Factory
{
    public function definition(): array
    {
        $event = WorldEvent::factory()->create();

        return [
            'event_id' => $event->id,
            'message_id' => null,
            'scene_id' => Scene::query()->active()->value('id') ?? Scene::factory()->active()->create()->id,
            'copilot_request_id' => null,
            'excerpt' => 'Test excerpt.',
        ];
    }
}
