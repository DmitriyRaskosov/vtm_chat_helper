<?php

namespace Database\Factories;

use App\Enums\CharacterHealthDamage;
use App\Models\Character;
use App\Models\CharacterHealthBox;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterHealthBox>
 */
class CharacterHealthBoxFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'box_index' => 0,
            'damage' => CharacterHealthDamage::Bashing,
        ];
    }
}
