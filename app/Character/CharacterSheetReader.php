<?php

namespace App\Character;

use App\Models\CanonDiscipline;
use App\Models\Character;
use Illuminate\Support\Facades\DB;

class CharacterSheetReader
{
    /**
     * @return array<string, mixed>
     */
    public function aggregate(Character $character): array
    {
        $character->load([
            'entity',
            'sect',
            'biography',
            'user',
            'clan',
            'sire.entity',
        ]);

        $biography = $character->biography;
        $sheet = is_array($character->sheet_data)
            ? CharacterStatsService::normalizeSheetData($character->sheet_data)
            : CharacterStatsService::emptySheetData();

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
            'state' => CharacterStatsService::legacyState(is_array($character->stats) ? $character->stats : null),
            'stats' => $character->stats,
            'sheet_schema' => config('sheet_schema'),
            'sheet_data' => $sheet,
            'available_disciplines' => $this->availableDisciplines($character),
        ];
    }

    /**
     * @return list<array{id: int, slug: string, name: string, is_clan_discipline: bool}>
     */
    private function availableDisciplines(Character $character): array
    {
        $clanDisciplineIds = [];
        if ($character->clan_id !== null) {
            $clanDisciplineIds = DB::table('canon_clan_disciplines')
                ->where('clan_id', $character->clan_id)
                ->where('is_in_clan', true)
                ->pluck('discipline_id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();
        }

        return CanonDiscipline::query()
            ->orderBy('name')
            ->get()
            ->map(fn (CanonDiscipline $discipline): array => [
                'id' => (int) $discipline->id,
                'slug' => $discipline->slug,
                'name' => $discipline->name,
                'is_clan_discipline' => in_array((int) $discipline->id, $clanDisciplineIds, true),
            ])
            ->values()
            ->all();
    }
}
