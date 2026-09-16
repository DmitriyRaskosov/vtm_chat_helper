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
     * @param  array{sect_entity_id?: int|null, clan_entity_id?: int|null, haven_entity_id?: int|null, lore_clearance_levels?: list<int>}  $fields
     */
    public function apply(Character $character, array $fields): Character
    {
        return DB::transaction(function () use ($character, $fields): Character {
            if (array_key_exists('sect_entity_id', $fields)) {
                $this->relations->setSect($character, $this->optionalEntity($fields['sect_entity_id']));
            }

            if (array_key_exists('clan_entity_id', $fields)) {
                $this->identity->update($character, ['clan_entity_id' => $fields['clan_entity_id']]);
            }

            if (array_key_exists('haven_entity_id', $fields)) {
                $this->relations->setHaven($character, $this->optionalEntity($fields['haven_entity_id']));
            }

            if (array_key_exists('lore_clearance_levels', $fields)) {
                $levels = array_values(array_unique(array_map('intval', $fields['lore_clearance_levels'])));
                sort($levels);
                if ($levels === []) {
                    throw new \InvalidArgumentException('At least one lore clearance level is required.');
                }
                foreach ($levels as $level) {
                    if ($level < 0 || $level > 5) {
                        throw new \InvalidArgumentException('Lore clearance levels must be integers between 0 and 5.');
                    }
                }
                $character->lore_clearance_levels = $levels;
                $character->save();
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
