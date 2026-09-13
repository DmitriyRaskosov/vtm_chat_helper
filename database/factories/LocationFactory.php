<?php

namespace Database\Factories;

use App\Enums\LocationType;
use App\Enums\WorldEntityType;
use App\Models\Location;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => WorldEntity::factory()->state(['entity_type' => WorldEntityType::Location]),
            'chronicle_id' => fn (array $attributes) => WorldEntity::query()
                ->findOrFail($attributes['id'])
                ->chronicle_id,
            'entity_type' => WorldEntityType::Location,
            'parent_location_id' => null,
            'location_type' => LocationType::Site,
            'details' => null,
        ];
    }
}
