<?php

namespace App\Character;

use App\Models\Character;
use App\Models\WorldEntity;
use App\World\WorldRelationService;
use Illuminate\Support\Facades\DB;

class CharacterPlaceService
{
    public function __construct(
        private WorldRelationService $relations,
        private CharacterIdentityService $identity,
    ) {}

    /** 
    @param  array{sect_id?: int|null, clan_id?: int|null, haven_entity_id?: int|null, lore_clearance_levels?: list<int>}  $fields
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

            if (array_key_exists('haven_entity_id', $fields)) {
                $this->relations->setHaven($character, $this->optionalEntity($fields['haven_entity_id']));
            }

            return $character->refresh();
        });
    }

    private function optionalEntity(mixed $id): ?WorldEntity
    {
        if ($id === null || $id === '') {
            return null;
        }

        return WorldEntity::query()->findOrFail((int) $id);
    }
}
