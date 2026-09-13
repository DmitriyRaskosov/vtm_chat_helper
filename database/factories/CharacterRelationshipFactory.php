<?php

namespace Database\Factories;

use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\CharacterRelationship;
use App\Models\WorldEntity;
use App\Models\WorldRelationType;
use App\World\WorldRelationService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterRelationship>
 */
class CharacterRelationshipFactory extends Factory
{
    public function definition(): array
    {
        $source = Character::factory()->create();
        $targetEntity = WorldEntity::factory()->create([
            'chronicle_id' => $source->chronicle_id,
            'entity_type' => WorldEntityType::Character,
        ]);
        $target = Character::factory()->create(['id' => $targetEntity->id]);

        $edge = app(WorldRelationService::class)->relate(
            WorldEntity::query()->findOrFail($source->id),
            WorldEntity::query()->findOrFail($target->id),
            WorldRelationType::query()->where('key', 'knows')->firstOrFail(),
        );

        return [
            'id' => $edge->id,
            'chronicle_id' => $source->chronicle_id,
            'source_character_id' => $source->id,
            'target_character_id' => $target->id,
        ];
    }
}
