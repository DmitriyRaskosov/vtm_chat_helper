<?php

namespace App\Character;

use App\Enums\CharacterStatCategory;
use App\Models\Character;
use App\Models\CharacterStat;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Builder;

class CharacterSheetReader
{
    public function __construct(private CharacterAffiliationService $affiliations) {}

    public function full(Character $character): CharacterSheet
    {
        return $this->relevant($character);
    }

    /**
     * Limited sheet for a future prompt: filter by category, key, and/or minimum value.
     *
     * @param  list<CharacterStatCategory|string>  $categories
     * @param  list<string>  $statKeys
     */
    public function relevant(
        Character $character,
        array $categories = [],
        array $statKeys = [],
        ?int $minValue = null,
    ): CharacterSheet {
        $stats = CharacterStat::query()
            ->where('character_id', $character->id)
            ->with(['specializations' => fn ($query) => $query->orderBy('id')])
            ->when($categories !== [], function (Builder $query) use ($categories): void {
                $values = array_map(
                    fn (CharacterStatCategory|string $category): string => $category instanceof CharacterStatCategory
                        ? $category->value
                        : $category,
                    $categories,
                );
                $query->whereIn('category', $values);
            })
            ->when($statKeys !== [], fn (Builder $query) => $query->whereIn('stat_key', $statKeys))
            ->when($minValue !== null, fn (Builder $query) => $query->where('value', '>=', $minValue))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return new CharacterSheet((int) $character->id, $stats);
    }

    /**
     * @return array<string, mixed>
     */
    public function aggregate(Character $character): array
    {
        $character->load([
            'stats.specializations',
            'disciplines.discipline',
            'status',
            'meritsFlaws',
            'healthBoxes',
            'biography',
            'domitor',
            'ghouls',
            'user',
            'clan',
            'sire',
        ]);

        $entity = WorldEntity::query()->findOrFail($character->id);
        $stats = $character->stats
            ->sortBy(['sort_order', 'id'])
            ->values()
            ->map(fn ($stat): array => [
                'category' => $stat->category->value,
                'stat_key' => $stat->stat_key,
                'display_name' => $stat->display_name,
                'value' => (int) $stat->value,
                'maximum' => $stat->maximum === null ? null : (int) $stat->maximum,
                'sort_order' => (int) $stat->sort_order,
                'specializations' => $stat->specializations->map(fn ($spec): array => [
                    'name' => $spec->name,
                    'description' => $spec->description,
                    'is_active' => (bool) $spec->is_active,
                ])->values()->all(),
            ])
            ->all();

        $status = $character->status;
        $healthByIndex = $character->healthBoxes->keyBy('box_index');
        $healthBoxes = [];
        foreach (range(0, 6) as $index) {
            $box = $healthByIndex->get($index);
            $healthBoxes[] = [
                'index' => $index,
                'damage' => $box?->damage?->value,
            ];
        }

        $domitor = $character->domitor;
        $domitorName = $domitor === null
            ? null
            : WorldEntity::query()->whereKey($domitor->id)->value('canonical_name');

        $ghoulIds = $character->ghouls->pluck('id')->all();
        $ghoulNames = $ghoulIds === []
            ? collect()
            : WorldEntity::query()->whereIn('id', $ghoulIds)->pluck('canonical_name', 'id');

        $biography = $character->biography;
        $sect = $this->affiliations->activeSect($character);
        $haven = $this->affiliations->activeHaven($character);

        return [
            'id' => (int) $character->id,
            'chronicle_id' => (int) $character->chronicle_id,
            'character_type' => $character->character_type->value,
            'canonical_name' => $entity->canonical_name,
            'user_id' => $character->user_id === null ? null : (int) $character->user_id,
            'clan_entity_id' => $character->clan_entity_id === null ? null : (int) $character->clan_entity_id,
            'clan_name' => $character->clan?->canonical_name,
            'sect_entity_id' => $sect === null ? null : (int) $sect->target_entity_id,
            'sect_name' => $sect?->target?->canonical_name,
            'haven_entity_id' => $haven === null ? null : (int) $haven->target_entity_id,
            'haven_name' => $haven?->target?->canonical_name,
            'lore_clearance_levels' => array_values(array_map(
                'intval',
                is_array($character->lore_clearance_levels) ? $character->lore_clearance_levels : [0],
            )),
            'sire_character_id' => $character->sire_character_id === null ? null : (int) $character->sire_character_id,
            'sire_name' => $character->sire === null
                ? null
                : WorldEntity::query()->whereKey($character->sire->id)->value('canonical_name'),
            'domitor_character_id' => $character->domitor_character_id === null ? null : (int) $character->domitor_character_id,
            'domitor_name' => is_string($domitorName) ? $domitorName : null,
            'generation' => $character->generation,
            'nature' => $character->nature,
            'demeanor' => $character->demeanor,
            'concept' => $character->concept,
            'experience' => (int) $character->experience,
            'is_active' => (bool) $character->is_active,
            'stats' => $stats,
            'disciplines' => $character->disciplines->map(fn ($row): array => [
                'discipline_id' => (int) $row->discipline_id,
                'key' => $row->discipline?->key,
                'display_name' => $row->discipline?->display_name,
                'level' => (int) $row->level,
            ])->values()->all(),
            'status' => $status === null ? null : [
                'temporary_willpower' => (int) $status->temporary_willpower,
                'blood_pool' => (int) $status->blood_pool,
                'health_state' => $status->health_state->value,
                'revision' => (int) $status->revision,
            ],
            'health_boxes' => $healthBoxes,
            'merits_flaws' => $character->meritsFlaws
                ->sortBy('sort_order')
                ->values()
                ->map(fn ($row): array => [
                    'id' => (int) $row->id,
                    'kind' => $row->kind->value,
                    'name' => $row->name,
                    'cost' => (int) $row->cost,
                    'note' => $row->note,
                ])
                ->all(),
            'ghouls' => $character->ghouls->map(fn ($ghoul): array => [
                'id' => (int) $ghoul->id,
                'canonical_name' => (string) ($ghoulNames[$ghoul->id] ?? $ghoulNames[(string) $ghoul->id] ?? ''),
                'character_type' => $ghoul->character_type->value,
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
