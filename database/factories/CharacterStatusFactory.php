<?php

namespace Database\Factories;

use App\Enums\CharacterHealthState;
use App\Models\Character;
use App\Models\CharacterStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterStatus>
 */
class CharacterStatusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'chronicle_id' => fn (array $attributes) => Character::query()
                ->findOrFail($attributes['character_id'])
                ->chronicle_id,
            'temporary_willpower' => 0,
            'blood_pool' => 0,
            'hunger' => 0,
            'health_state' => CharacterHealthState::Healthy,
            'fatigue' => 0,
            'current_location_id' => null,
            'revision' => 1,
        ];
    }
}
