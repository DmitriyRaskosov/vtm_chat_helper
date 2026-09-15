<?php

namespace Database\Factories;

use App\Enums\FactionStatus;
use App\Enums\WorldEntityType;
use App\Models\Other;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Other>
 */
class OtherFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => WorldEntity::factory()->state(['entity_type' => WorldEntityType::Other]),
            'chronicle_id' => fn (array $attributes) => WorldEntity::query()
                ->findOrFail($attributes['id'])
                ->chronicle_id,
            'entity_type' => WorldEntityType::Other,
            'status' => FactionStatus::Active,
        ];
    }
}
