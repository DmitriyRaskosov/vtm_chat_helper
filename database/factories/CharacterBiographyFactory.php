<?php

namespace Database\Factories;

use App\Enums\CharacterBiographyStatus;
use App\Models\Character;
use App\Models\CharacterBiography;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterBiography>
 */
class CharacterBiographyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'summary' => 'A fledgling of the Camarilla.',
            'full_text' => 'She keeps the Masquerade and answers to her sire.',
            'principles' => null,
            'motivation' => null,
            'fears' => null,
            'desires' => null,
            'behavioral_rules' => null,
            'current_version' => 1,
            'status' => CharacterBiographyStatus::Draft,
            'approved_at' => null,
            'approved_by' => null,
        ];
    }
}
