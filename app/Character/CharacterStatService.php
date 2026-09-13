<?php

namespace App\Character;

use App\Enums\CharacterStatCategory;
use App\Models\Character;
use App\Models\CharacterStat;
use App\Models\CharacterStatSpecialization;
use InvalidArgumentException;

class CharacterStatService
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function putStat(
        Character $character,
        CharacterStatCategory $category,
        string $statKey,
        string $displayName,
        int $value,
        ?int $maximum = null,
        int $sortOrder = 0,
        ?array $metadata = null,
    ): CharacterStat {
        $statKey = trim($statKey);
        $displayName = trim($displayName);

        if ($statKey === '' || preg_match('/^[a-z][a-z0-9_]*$/', $statKey) !== 1) {
            throw new InvalidArgumentException('A stat key must be a lowercase snake_case identifier.');
        }

        if ($displayName === '') {
            throw new InvalidArgumentException('A stat display name is required.');
        }

        if ($value < 0) {
            throw new InvalidArgumentException('A stat value cannot be negative.');
        }

        if ($maximum !== null && ($maximum < 0 || $value > $maximum)) {
            throw new InvalidArgumentException('A stat value cannot exceed its maximum.');
        }

        return CharacterStat::query()->updateOrCreate(
            [
                'character_id' => $character->id,
                'category' => $category,
                'stat_key' => $statKey,
            ],
            [
                'display_name' => $displayName,
                'value' => $value,
                'maximum' => $maximum,
                'sort_order' => $sortOrder,
                'metadata' => $metadata,
            ],
        );
    }

    public function addSpecialization(
        CharacterStat $stat,
        string $name,
        ?string $description = null,
        bool $isActive = true,
    ): CharacterStatSpecialization {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('A specialization name is required.');
        }

        return CharacterStatSpecialization::query()->create([
            'character_stat_id' => $stat->id,
            'name' => $name,
            'description' => $description,
            'is_active' => $isActive,
        ]);
    }
}
