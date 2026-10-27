<?php

namespace App\Character;

use App\Chronicle\CurrentChronicle;
use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\World\WorldEntityService;

final class CharacterCreationService
{
    public function __construct(
        private CurrentChronicle $currentChronicle,
        private WorldEntityService $entities,
        private CharacterStateService $stateService,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated): Character
    {
        $chronicle = $this->currentChronicle->get();

        $typed = [
            'character_type' => CharacterType::from($validated['character_type']),
        ];

        if (isset($validated['user_id'])) {
            $typed['user_id'] = (int) $validated['user_id'];
        }
        if (isset($validated['clan_id'])) {
            $typed['clan_id'] = (int) $validated['clan_id'];
        }
        if (isset($validated['sire_character_id'])) {
            $typed['sire_character_id'] = (int) $validated['sire_character_id'];
        }
        foreach (['generation', 'nature', 'demeanor', 'concept'] as $field) {
            if (array_key_exists($field, $validated)) {
                $typed[$field] = $validated[$field];
            }
        }

        $entity = $this->entities->create(
            $chronicle,
            WorldEntityType::Character,
            $validated['canonical_name'],
            typed: $typed,
        );

        $character = Character::query()->findOrFail($entity->id);
        $this->stateService->ensure($character);

        return $character;
    }
}
