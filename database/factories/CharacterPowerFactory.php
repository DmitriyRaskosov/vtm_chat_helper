<?php

namespace Database\Factories;

use App\Models\Character;
use App\Models\CharacterDiscipline;
use App\Models\CharacterPower;
use App\Models\DisciplinePower;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterPower>
 */
class CharacterPowerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'character_id' => Character::factory(),
            'discipline_id' => 0,
            'discipline_power_id' => DisciplinePower::factory(),
            'acquired_at' => now(),
            'note' => null,
            'parameters' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (CharacterPower $learned): void {
            $power = DisciplinePower::query()->findOrFail($learned->discipline_power_id);
            $learned->discipline_id = $power->discipline_id;

            CharacterDiscipline::query()->firstOrCreate(
                [
                    'character_id' => $learned->character_id,
                    'discipline_id' => $power->discipline_id,
                ],
                ['level' => max(1, (int) $power->required_level)],
            );
        });
    }
}
