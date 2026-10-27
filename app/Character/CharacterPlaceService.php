<?php

namespace App\Character;

use App\Models\Character;
use Illuminate\Support\Facades\DB;

class CharacterPlaceService
{
    /**
     * @param  array{sect_id?: int|string|null, clan_id?: int|string|null, lore_clearance_levels?: list<int>}  $fields
     */
    public function apply(Character $character, array $fields): Character
    {
        return DB::transaction(function () use ($character, $fields): Character {
            if (array_key_exists('sect_id', $fields)) {
                $id = $fields['sect_id'];
                $character->sect_id = ($id === null || $id === '') ? null : (int) $id;
                $character->save();
            }

            if (array_key_exists('clan_id', $fields)) {
                $id = $fields['clan_id'];
                $character->clan_id = ($id === null || $id === '') ? null : (int) $id;
                $character->save();
            }

            return $character->refresh();
        });
    }
}
