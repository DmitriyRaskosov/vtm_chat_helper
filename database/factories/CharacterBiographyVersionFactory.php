<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterBiographyVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterBiographyVersion>
 */
class CharacterBiographyVersionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'version' => 1,
            'summary' => 'A fledgling of the Camarilla.',
            'full_text' => 'She keeps the Masquerade and answers to her sire.',
            'principles' => null,
            'motivation' => null,
            'fears' => null,
            'desires' => null,
            'behavioral_rules' => null,
            'change_reason' => 'Initial canon.',
            'created_by' => null,
        ];
    }
}
