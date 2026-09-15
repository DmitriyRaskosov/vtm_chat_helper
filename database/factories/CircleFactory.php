<?php

namespace Database\Factories;

use App\Enums\FactionStatus;
use App\Enums\WorldEntityType;
use App\Models\Circle;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Circle>
 */
class CircleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => WorldEntity::factory()->state(['entity_type' => WorldEntityType::Circle]),
            'chronicle_id' => fn (array $attributes) => WorldEntity::query()
                ->findOrFail($attributes['id'])
                ->chronicle_id,
            'entity_type' => WorldEntityType::Circle,
            'sect_faction_id' => null,
            'status' => FactionStatus::Active,
        ];
    }
}
