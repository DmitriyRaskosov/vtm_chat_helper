<?php

namespace Database\Factories;

use App\Enums\SceneParticipantRole;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SceneParticipant>
 */
class SceneParticipantFactory extends Factory
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
            'character_id' => function (array $attributes) {
                $entity = WorldEntity::factory()->create([
                    'chronicle_id' => $attributes['chronicle_id'],
                    'entity_type' => WorldEntityType::Character,
                ]);

                return Character::factory()->create([
                    'id' => $entity->id,
                    'chronicle_id' => $attributes['chronicle_id'],
                ])->id;
            },
            'role' => SceneParticipantRole::Npc,
            'visible' => true,
            'is_current' => true,
            'entered_at' => now(),
            'left_at' => null,
        ];
    }
}
