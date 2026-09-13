<?php

namespace App\Character;

use App\Models\Character;
use App\Models\WorldEntity;
use App\Models\WorldEntityAlias;
use App\World\AliasNormalizer;
use App\World\WorldEntityService;
use InvalidArgumentException;

class CharacterIdentityService
{
    public function __construct(private WorldEntityService $entities) {}

    /**
     * @param  array<string, mixed>  $fields
     */
    public function update(Character $character, array $fields): Character
    {
        if (array_key_exists('canonical_name', $fields)) {
            $name = trim((string) $fields['canonical_name']);
            if ($name === '') {
                throw new InvalidArgumentException('A character name is required.');
            }

            $this->rename($character, $name);
        }

        $typed = [];

        foreach (['nature', 'demeanor', 'concept'] as $field) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }

            $value = $fields[$field];
            $typed[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        if (array_key_exists('generation', $fields)) {
            $generation = $fields['generation'];
            $typed['generation'] = $generation === null || $generation === '' ? null : (int) $generation;
        }

        if (array_key_exists('clan_entity_id', $fields)) {
            $clanId = $fields['clan_entity_id'];
            $typed['clan_entity_id'] = $clanId === null || $clanId === '' ? null : (int) $clanId;
        }

        if (array_key_exists('sire_character_id', $fields)) {
            $sireId = $fields['sire_character_id'];
            $typed['sire_character_id'] = $sireId === null || $sireId === '' ? null : (int) $sireId;
        }

        if ($typed !== []) {
            $this->applyTyped($character, $typed);
        }

        return $character->refresh();
    }

    public function setExperience(Character $character, int $experience): Character
    {
        if ($experience < 0) {
            throw new InvalidArgumentException('Experience cannot be negative.');
        }

        $character->experience = $experience;
        $character->save();

        return $character->refresh();
    }

    private function rename(Character $character, string $name): void
    {
        $entity = WorldEntity::query()->findOrFail($character->id);
        $normalized = AliasNormalizer::normalize($name);

        $taken = WorldEntityAlias::query()
            ->where('chronicle_id', $entity->chronicle_id)
            ->where('normalized_alias', $normalized)
            ->where('entity_id', '!=', $entity->id)
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException('This name is already used in the chronicle.');
        }

        $entity->update(['canonical_name' => $name]);

        $canonical = $entity->canonicalAlias()->first();
        if ($canonical !== null) {
            $canonical->update([
                'alias' => $name,
                'normalized_alias' => $normalized,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function applyTyped(Character $character, array $typed): void
    {
        if (isset($typed['clan_entity_id'])) {
            $clan = WorldEntity::query()->findOrFail((int) $typed['clan_entity_id']);
            $this->entities->assertClanFaction($character->chronicle, $clan);
        }

        if (isset($typed['sire_character_id'])) {
            $sireId = (int) $typed['sire_character_id'];
            if ($sireId === (int) $character->id) {
                throw new InvalidArgumentException('A character cannot be their own sire.');
            }

            $sire = Character::query()->findOrFail($sireId);
            $this->entities->assertSameChronicle(
                $character->chronicle,
                WorldEntity::query()->findOrFail($sire->id),
            );
        }

        $character->fill($typed);
        $character->save();
    }
}
