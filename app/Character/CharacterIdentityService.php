<?php

namespace App\Character;

use App\Character\Exceptions\InvalidCharacterInputException;
use App\Models\CanonClan;
use App\Models\Character;
use App\Models\WorldEntity;
use App\World\WorldEntityService;
use Illuminate\Database\UniqueConstraintViolationException;

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
                throw new InvalidCharacterInputException('A character name is required.');
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

        $this->assignSheetText($character, $fields);

        if (array_key_exists('generation', $fields)) {
            $generation = $fields['generation'];
            $typed['generation'] = $generation === null || $generation === '' ? null : (int) $generation;
        }

        if (array_key_exists('clan_id', $fields)) {
            $clanId = $fields['clan_id'];
            $typed['clan_id'] = $clanId === null || $clanId === '' ? null : (int) $clanId;
        }

        if (array_key_exists('sire_character_id', $fields)) {
            $sireId = $fields['sire_character_id'];
            $typed['sire_character_id'] = $sireId === null || $sireId === '' ? null : (int) $sireId;
        }

        if ($typed !== []) {
            $this->applyTyped($character, $typed);
        } elseif ($character->isDirty('sheet_data')) {
            $character->save();
        }

        return $character->refresh();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function assignSheetText(Character $character, array $fields): void
    {
        $sheet = is_array($character->sheet_data) ? $character->sheet_data : [];
        $changed = false;

        foreach (['description', 'inventory'] as $field) {
            if (! array_key_exists($field, $fields)) {
                continue;
            }

            $value = $fields[$field];
            $sheet[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
            $changed = true;
        }

        if ($changed) {
            $character->sheet_data = CharacterStatsService::normalizeSheetData($sheet);
        }
    }

    private function rename(Character $character, string $name): void
    {
        $character->loadMissing('entity');
        $entity = $character->entity ?? WorldEntity::query()->findOrFail($character->id);
        $name = $this->entities->prepareCanonicalName($name);

        $this->entities->assertCanonicalNameAvailable(
            $character->chronicle,
            $name,
            (int) $entity->id,
        );

        try {
            $entity->update(['canonical_name' => $name]);
        } catch (UniqueConstraintViolationException) {
            throw new InvalidCharacterInputException('This name is already used in the chronicle.');
        }
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function applyTyped(Character $character, array $typed): void
    {
        if (array_key_exists('clan_id', $typed) && $typed['clan_id'] !== null
            && ! CanonClan::query()->whereKey((int) $typed['clan_id'])->exists()) {
            throw new InvalidCharacterInputException('The selected clan does not exist.');
        }

        if (isset($typed['sire_character_id'])) {
            $sireId = (int) $typed['sire_character_id'];
            if ($sireId === (int) $character->id) {
                throw new InvalidCharacterInputException('A character cannot be their own sire.');
            }

            $sire = Character::query()->findOrFail($sireId);
            $sire->loadMissing('entity');
            $sireEntity = $sire->entity ?? WorldEntity::query()->findOrFail($sire->id);
            $this->entities->assertSameChronicle(
                $character->chronicle,
                $sireEntity,
            );
        }

        $character->fill($typed);
        $character->save();
    }
}
