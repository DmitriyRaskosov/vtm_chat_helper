<?php

namespace Database\Factories;

use App\Enums\CharacterMeritKind;
use App\Models\Character;
use App\Models\CharacterMeritFlaw;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterMeritFlaw>
 */
class CharacterMeritFlawFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'kind' => CharacterMeritKind::Merit,
            'name' => 'Medium',
            'cost' => 2,
            'note' => null,
            'sort_order' => 0,
        ];
    }
}
