<?php

namespace Database\Factories;

use App\Models\Scene;
use App\Models\SceneContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SceneContext>
 */
class SceneContextFactory extends Factory
{
    public function definition(): array
    {
        return [
            'scene_id' => Scene::factory(),
            'chronicle_id' => fn (array $attributes) => Scene::query()
                ->with('gameSession')
                ->findOrFail($attributes['scene_id'])
                ->gameSession
                ->chronicle_id,
            'location_entity_id' => null,
            'atmosphere' => null,
            'situation' => null,
            'storyteller_notes' => null,
            'revision' => 0,
            'frozen_revision' => null,
            'updated_by' => null,
        ];
    }
}
