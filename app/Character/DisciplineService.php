<?php

namespace App\Character;

use App\Models\CanonDiscipline;
use App\Models\CanonDisciplinePower;
use App\Models\Character;
use App\Models\CharacterDiscipline;
use App\Models\CharacterPower;
use InvalidArgumentException;

class DisciplineService
{
    public function setCharacterDiscipline(
        Character $character,
        CanonDiscipline $discipline,
        int $level,
    ): CharacterDiscipline {
        if ($level < 1 || $level > 9) {
            throw new InvalidArgumentException('A discipline level must be between 1 and 9.');
        }

        return CharacterDiscipline::query()->updateOrCreate(
            [
                'character_id' => $character->id,
                'discipline_id' => $discipline->id,
            ],
            ['level' => $level],
        );
    }

    public function clearCharacterDiscipline(Character $character, CanonDiscipline $discipline): void
    {
        $known = CharacterDiscipline::query()
            ->where('character_id', $character->id)
            ->where('discipline_id', $discipline->id)
            ->first();

        if ($known === null) {
            return;
        }

        if (CharacterPower::query()
            ->where('character_id', $character->id)
            ->where('discipline_id', $discipline->id)
            ->exists()) {
            throw new InvalidArgumentException('Remove learned powers before clearing a discipline.');
        }

        $known->delete();
    }

    /**
     * @param  array<string, mixed>|null  $parameters
     */
    public function learnPower(
        Character $character,
        CanonDisciplinePower $power,
        ?string $note = null,
        ?array $parameters = null,
        mixed $acquiredAt = null,
    ): CharacterPower {
        $known = CharacterDiscipline::query()
            ->where('character_id', $character->id)
            ->where('discipline_id', $power->discipline_id)
            ->first();

        if ($known === null) {
            throw new InvalidArgumentException('A character must know the discipline before learning a power.');
        }

        if ($known->level < $power->level) {
            throw new InvalidArgumentException('Character discipline level is below the power level.');
        }

        return CharacterPower::query()->create([
            'character_id' => $character->id,
            'discipline_id' => $power->discipline_id,
            'discipline_power_id' => $power->id,
            'acquired_at' => $acquiredAt ?? now(),
            'note' => $note,
            'parameters' => $parameters,
        ]);
    }
}