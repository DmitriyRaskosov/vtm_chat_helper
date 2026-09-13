<?php

namespace Database\Factories;

use App\Enums\FactionStatus;
use App\Enums\FactionType;
use App\Enums\WorldEntityType;
use App\Models\Faction;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Faction>
 */
class FactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => WorldEntity::factory()->state(['entity_type' => WorldEntityType::Faction]),
            'chronicle_id' => fn (array $attributes) => WorldEntity::query()
                ->findOrFail($attributes['id'])
                ->chronicle_id,
            'entity_type' => WorldEntityType::Faction,
            'parent_faction_id' => null,
            'faction_type' => FactionType::Other,
            'status' => FactionStatus::Active,
        ];
    }
}
