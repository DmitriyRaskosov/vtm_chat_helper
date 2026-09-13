<?php

namespace App\Character;

use App\Models\Character;
use App\Models\CharacterDiscipline;
use App\Models\CharacterPower;
use App\Models\Discipline;
use App\Models\DisciplinePower;
use InvalidArgumentException;

class DisciplineService
{
    public function createDiscipline(string $ruleset, string $key, string $displayName): Discipline
    {
        return Discipline::query()->create([
            'ruleset' => $this->identifier($ruleset, 'ruleset'),
            'key' => $this->identifier($key, 'discipline key'),
            'display_name' => $this->requiredName($displayName),
        ]);
    }

    public function addPower(
        Discipline $discipline,
        string $key,
        string $displayName,
        int $requiredLevel,
        ?string $ruleKey = null,
    ): DisciplinePower {
        if ($requiredLevel < 1 || $requiredLevel > 9) {
            throw new InvalidArgumentException('A power required level must be between 1 and 9.');
        }

        return DisciplinePower::query()->create([
            'discipline_id' => $discipline->id,
            'key' => $this->identifier($key, 'power key'),
            'display_name' => $this->requiredName($displayName),
            'required_level' => $requiredLevel,
            'rule_key' => $ruleKey === null || $ruleKey === '' ? null : $this->identifier($ruleKey, 'rule key'),
        ]);
    }

    public function setCharacterDiscipline(Character $character, Discipline $discipline, int $level): CharacterDiscipline
    {
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

    public function clearCharacterDiscipline(Character $character, Discipline $discipline): void
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
        DisciplinePower $power,
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

        if ($known->level < $power->required_level) {
            throw new InvalidArgumentException('Character discipline level is below the power required level.');
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

    private function identifier(string $value, string $label): string
    {
        $value = trim($value);

        if ($value === '' || preg_match('/^[a-z][a-z0-9_-]*$/', $value) !== 1) {
            throw new InvalidArgumentException("A {$label} must be a lowercase identifier.");
        }

        return $value;
    }

    private function requiredName(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('A display name is required.');
        }

        return $value;
    }
}
