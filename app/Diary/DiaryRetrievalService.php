<?php

namespace App\Diary;

use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Rag\EmbeddingProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DiaryRetrievalService
{
    private const MIN_SIMILARITY = 0.45;
    private const MAX_ENTRIES = 3;
    private const CANDIDATE_LIMIT = 30;

    public function __construct(
        private EmbeddingProvider $embeddings,
    ) {}

    /**
     * @return Collection<int, CharacterDiaryEntry>
     */
    public function retrieve(Character $npc, string $query, int $limit = self::MAX_ENTRIES): Collection
    {
        $all = CharacterDiaryEntry::query()
            ->where('character_id', $npc->id)
            ->where('is_stale', false)
            ->orderBy('created_at')
            ->get();

        if ($all->isEmpty()) {
            return collect();
        }

        $pool = $this->buildPool($all);
        if ($pool->isEmpty()) {
            return collect();
        }

        $lastEntry = $pool->sortByDesc('id')->first();
        $result = collect([$lastEntry]);
        $usedIds = [(int) $lastEntry->id];

        $query = trim($query);
        if ($query !== '' && $pool->count() > 1 && $limit > 1) {
            $others = $pool->reject(fn ($e) => in_array((int) $e->id, $usedIds, true));
            $semantic = $this->findSemantic(
                $query,
                $others->pluck('id')->map(fn ($id): int => (int) $id)->all(),
                $limit - 1,
            );

            foreach ($semantic as $entry) {
                if (! in_array((int) $entry->id, $usedIds, true)) {
                    $result->push($entry);
                    $usedIds[] = (int) $entry->id;
                }
            }
        }

        return $result->take($limit)->values();
    }

    /**
     * Один пул записей на сцену: если есть L1 — берём только его,
     * иначе все L0 этой сцены.
     *
     * @param  Collection<int, CharacterDiaryEntry>  $all
     * @return Collection<int, CharacterDiaryEntry>
     */
    private function buildPool(Collection $all): Collection
    {
        $pool = collect();

        foreach ($all->groupBy('scene_id') as $entries) {
            $l1 = $entries->firstWhere('level', 1);
            if ($l1 !== null) {
                $pool->push($l1);
                continue;
            }
            foreach ($entries->where('level', 0) as $l0) {
                $pool->push($l0);
            }
        }

        return $pool;
    }

    /**
     * @param  list<int>  $poolIds
     * @return Collection<int, CharacterDiaryEntry>
     */
    private function findSemantic(string $query, array $poolIds, int $limit): Collection
    {
        if ($poolIds === [] || $limit <= 0) {
            return collect();
        }

        $vector = $this->embeddings->embed($query);
        $pgVector = $this->formatVector($vector);
        $pgArray = '{'.implode(',', $poolIds).'}';

        $rows = DB::select(
            'SELECT id, 1 - (embedding <=> ?::vector) AS similarity
             FROM character_diary_entries
             WHERE id = ANY(?::bigint[])
             ORDER BY embedding <=> ?::vector
             LIMIT ?',
            [$pgVector, $pgArray, $pgVector, self::CANDIDATE_LIMIT],
        );

        $ids = [];
        foreach ($rows as $row) {
            $sim = (float) $row->similarity;
            if ($sim < self::MIN_SIMILARITY) {
                break;
            }
            $ids[] = (int) $row->id;
            if (count($ids) >= $limit) {
                break;
            }
        }

        if ($ids === []) {
            return collect();
        }

        $models = CharacterDiaryEntry::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)
            ->map(fn (int $id) => $models->get($id))
            ->filter()
            ->values();
    }

    /**
     * @param  list<float>  $vector
     */
    private function formatVector(array $vector): string
    {
        return '['.implode(',', array_map(fn (float $v): string => (string) $v, $vector)).']';
    }
}