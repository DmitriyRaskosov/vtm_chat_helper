<?php

namespace App\Character;

use App\Models\Character;
use App\Models\CharacterStat;
use Illuminate\Support\Collection;

final readonly class CharacterSheet
{
    /**
     * @param  Collection<int, CharacterStat>  $stats
     */
    public function __construct(
        public int $characterId,
        public Collection $stats,
    ) {}

    public function forCharacter(Character $character): bool
    {
        return $this->characterId === (int) $character->id;
    }
}
