<?php

namespace App\Character;

use App\Character\Exceptions\InvalidDisciplineInputException;
use App\Models\CanonDiscipline;
use App\Models\Character;
use App\Models\CharacterDiscipline;
use App\Models\CharacterPower;
use Illuminate\Support\Facades\DB;

class DisciplineService
{
    /**
     * @param  list<array{discipline_id: int|string, level: int|string}>  $rows
     */
    public function sync(Character $character, array $rows): void
    {
        DB::transaction(function () use ($character, $rows): void {
            foreach ($rows as $row) {
                $discipline = CanonDiscipline::query()->findOrFail((int) $row['discipline_id']);
                $level = (int) $row['level'];
                if ($level === 0) {
                    $this->clearCharacterDiscipline($character, $discipline);

                    continue;
                }

                $this->setCharacterDiscipline($character, $discipline, $level);
            }
        });
    }

    public function setCharacterDiscipline(
        Character $character,
        CanonDiscipline $discipline,
        int $level,
    ): CharacterDiscipline {
        if ($level < 1 || $level > 9) {
            throw new InvalidDisciplineInputException('A discipline level must be between 1 and 9.');
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
            throw new InvalidDisciplineInputException('Remove learned powers before clearing a discipline.');
        }

        $known->delete();
    }
}
