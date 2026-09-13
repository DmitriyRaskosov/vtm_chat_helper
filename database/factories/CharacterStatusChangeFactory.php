<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterStatusChange;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterStatusChange>
 */
class CharacterStatusChangeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'field' => 'hunger',
            'old_value' => ['v' => 0],
            'new_value' => ['v' => 1],
            'reason' => 'Test.',
            'scene_id' => null,
            'message_id' => null,
            'game_time' => null,
            'changed_by' => null,
            'revision' => 2,
        ];
    }
}
