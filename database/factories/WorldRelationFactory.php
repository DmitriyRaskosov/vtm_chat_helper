<?php

namespace Database\Factories;

use App\Enums\WorldEntityType;
use App\Models\WorldEntity;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorldRelation>
 */
class WorldRelationFactory extends Factory
{
    public function definition(): array
    {
        $source = WorldEntity::factory()->create(['entity_type' => WorldEntityType::Character]);
        $target = WorldEntity::factory()->create([
            'chronicle_id' => $source->chronicle_id,
            'entity_type' => WorldEntityType::Faction,
        ]);

        return [
            'chronicle_id' => $source->chronicle_id,
            'source_entity_id' => $source->id,
            'target_entity_id' => $target->id,
            'relation_type_id' => fn () => WorldRelationType::query()->where('key', 'member_of')->value('id'),
            'weight' => 1,
            'note' => null,
            'started_at' => now(),
            'ended_at' => null,
            'provenance' => [],
        ];
    }
}
