<?php

namespace Database\Factories;

use App\Enums\WorldEntityType;
use App\Models\WorldEntity;
use App\Models\WorldRelation;
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
            'source_type' => WorldEntityType::Character->value,
            'source_id' => $source->id,
            'target_type' => WorldEntityType::Faction->value,
            'target_id' => $target->id,
            'relation' => 'member_of',
            'source_of_truth' => 'chronicle',
            'intensity' => null,
            'metadata' => null,
            'weight' => 1,
            'note' => null,
            'valid_from' => now(),
            'valid_to' => null,
            'provenance' => [],
        ];
    }
}
