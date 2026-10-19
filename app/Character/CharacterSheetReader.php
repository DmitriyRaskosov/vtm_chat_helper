<?php

namespace App\Character;

use App\Models\Character;

class CharacterSheetReader
{
    public function __construct(
        private CharacterStateService $stateService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function aggregate(Character $character): array
    {
        $character->load([
            'entity',
            'sect',
            'biography',
            'traits',
            'user',
            'clan',
            'sire.entity',
            'state',
        ]);

        $biography = $character->biography;

        return [
            'id' => (int) $character->id,
            'chronicle_id' => (int) $character->chronicle_id,
            'character_type' => $character->character_type->value,
            'canonical_name' => $character->entity === null
                ? ''
                : (string) $character->entity->canonical_name,
            'user_id' => $character->user_id === null ? null : (int) $character->user_id,
            'clan_id' => $character->clan_id === null ? null : (int) $character->clan_id,
            'clan' => $character->clan ? [
                'id' => $character->clan->id,
                'slug' => $character->clan->slug,
                'name' => $character->clan->name,
                'nickname' => $character->clan->nickname,
            ] : null,
            'sect_id' => $character->sect_id === null ? null : (int) $character->sect_id,
            'sect' => $character->sect ? [
                'id' => $character->sect->id,
                'slug' => $character->sect->slug,
                'name' => $character->sect->name,
            ] : null,
            'sire_character_id' => $character->sire_character_id === null ? null : (int) $character->sire_character_id,
            'sire_name' => $character->sire?->entity?->canonical_name,
            'generation' => $character->generation,
            'nature' => $character->nature,
            'demeanor' => $character->demeanor,
            'concept' => $character->concept,
            'is_active' => (bool) $character->is_active,
            'disciplines' => $character->disciplines->map(fn ($row): array => [
                'discipline_id' => (int) $row->discipline_id,
                'slug' => $row->discipline?->slug,
                'name' => $row->discipline?->name,
                'level' => (int) $row->level,
            ])->values()->all(),
            'biography' => $biography === null ? null : [
                'summary' => $biography->summary,
                'full_text' => $biography->full_text,
                'principles' => $biography->principles,
                'motivation' => $biography->motivation,
                'fears' => $biography->fears,
                'desires' => $biography->desires,
                'behavioral_rules' => $biography->behavioral_rules,
                'current_version' => (int) $biography->current_version,
                'status' => $biography->status->value,
            ],
            'traits' => $character->traits->map(fn ($trait): array => [
                'id' => (int) $trait->id,
                'key' => $trait->key,
                'label' => $trait->label,
                'value' => $trait->value,
                'sort_order' => (int) $trait->sort_order,
            ])->values()->all(),
            'state' => $this->stateService->toArray($this->stateService->ensure($character)),
        ];
    }
}
