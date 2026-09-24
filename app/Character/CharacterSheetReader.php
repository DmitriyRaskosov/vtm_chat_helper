<?php

namespace App\Character;

use App\Models\Character;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Builder;

class CharacterSheetReader
{
    public function __construct(private \App\World\WorldRelationService $relations) {}

    /**
     * @return array<string, mixed>
     */
    public function aggregate(Character $character): array
    {
        $character->load([
            'sect',
            'biography',
            'user',
            'clan',
            'sire',
        ]);

        $entity = WorldEntity::query()->findOrFail($character->id);

        $biography = $character->biography;
        $sect = $this->relations->activeSect($character);
        $haven = $this->relations->activeHaven($character);

        return [
            'id' => (int) $character->id,
            'chronicle_id' => (int) $character->chronicle_id,
            'character_type' => $character->character_type->value,
            'canonical_name' => $entity->canonical_name,
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
            'haven_entity_id' => $haven === null ? null : (int) $haven->target_id,
            'haven_name' => $haven?->target?->canonical_name,
            'sire_character_id' => $character->sire_character_id === null ? null : (int) $character->sire_character_id,
            'sire_name' => $character->sire === null
                ? null
                : WorldEntity::query()->whereKey($character->sire->id)->value('canonical_name'),
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
        ];
    }
}
