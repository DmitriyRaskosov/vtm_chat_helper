<?php

namespace Database\Factories;

use App\Enums\FactionStatus;
use App\Enums\WorldEntityType;
use App\Models\Clan;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clan>
 */
class ClanFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => WorldEntity::factory()->state(['entity_type' => WorldEntityType::Clan]),
            'chronicle_id' => fn (array $attributes) => WorldEntity::query()
                ->findOrFail($attributes['id'])
                ->chronicle_id,
            'entity_type' => WorldEntityType::Clan,
            'sect_faction_id' => null,
            'status' => FactionStatus::Active,
        ];
    }
}
