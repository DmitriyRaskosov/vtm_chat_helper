<?php

namespace Database\Factories;

use App\Enums\WorldEntityType;
use App\Models\Concept;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Concept>
 */
class ConceptFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => WorldEntity::factory()->state(['entity_type' => WorldEntityType::Concept]),
            'chronicle_id' => fn (array $attributes) => WorldEntity::query()
                ->findOrFail($attributes['id'])
                ->chronicle_id,
            'entity_type' => WorldEntityType::Concept,
            'definition' => null,
        ];
    }
}
