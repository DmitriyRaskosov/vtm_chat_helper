<?php

namespace App\Character;

use App\Chronicle\CurrentChronicle;
use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use App\Events\CharacterEditedByStorytellerEvent;
use App\Models\Character;
use App\Models\User;
use App\World\WorldEntityService;

final class CharacterCreationService
{
    public function __construct(
        private CurrentChronicle $currentChronicle,
        private WorldEntityService $entities,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function create(array $validated, User $actor): Character
    {
        $chronicle = $this->currentChronicle->get();

        if (! $actor->isStorytellerIn($chronicle)) {
            $validated['character_type'] = CharacterType::Player->value;
            $validated['user_id'] = $actor->id;
        }

        $characterType = CharacterType::from($validated['character_type']);

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
        $character->stats = CharacterStatsService::defaults();
        $character->save();

        return $character;
    }

    public function recordStorytellerEdit(User $actor, Character $character): void
    {
        $character->loadMissing('chronicle');

        if ($character->character_type !== CharacterType::Player) {
            return;
        }

        if ((int) $character->user_id === (int) $actor->id) {
            return;
        }

        $chronicle = $character->chronicle;
        if ($chronicle === null || ! $actor->isStorytellerIn($chronicle)) {
            return;
        }

        $character->updated_by_storyteller_at = now();
        $character->save();

        CharacterEditedByStorytellerEvent::dispatch(
            (int) $chronicle->id,
            (int) $character->id,
            (int) $character->user_id,
        );
    }

    public function acknowledgeStorytellerEdit(Character $character): void
    {
        $character->updated_by_storyteller_at = null;
        $character->save();
    }
}
