<?php

namespace App\Character;

use App\Character\Exceptions\PlayerCharacterLimitException;
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
        $characterType = CharacterType::from($validated['character_type']);

        if ($characterType === CharacterType::Player && isset($validated['user_id'])) {
            $this->assertActivePlayerSlotIsFree((int) $chronicle->id, (int) $validated['user_id']);
        }

        $typed = [
            'character_type' => $characterType,
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

    private function assertActivePlayerSlotIsFree(int $chronicleId, int $userId): void
    {
        $taken = Character::query()
            ->where('chronicle_id', $chronicleId)
            ->where('user_id', $userId)
            ->where('character_type', CharacterType::Player)
            ->where('is_active', true)
            ->exists();

        if ($taken) {
            throw new PlayerCharacterLimitException;
        }
    }
}
