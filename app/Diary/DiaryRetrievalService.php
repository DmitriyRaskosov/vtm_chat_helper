<?php

namespace App\Diary;

use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Rag\EmbeddingProvider;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DiaryRetrievalService
{
    private const MIN_SIMILARITY = 0.45;

    public const MAX_ENTRIES = 3;

    private const CANDIDATE_LIMIT = 30;

    public function __construct(
        private EmbeddingProvider $embeddings,
    ) {}

    /**
     * @return Collection<int, CharacterDiaryEntry>
     */
    public function retrieve(
        Character $npc,
        string $query,
        int $limit = self::MAX_ENTRIES,
        ?int $recentFirstMessageId = null,
        ?int $currentSceneId = null,
    ): Collection {
        return $this->retrieveWithDiagnostics(
            $npc,
            $query,
            $limit,
            $recentFirstMessageId,
            $currentSceneId,
        )['selected'];
    }

    /**
     * @return array{selected: Collection<int, CharacterDiaryEntry>, stats: array<string, int>}
     */
    public function retrieveWithDiagnostics(
        Character $npc,
        string $query,
        int $limit = self::MAX_ENTRIES,
        ?int $recentFirstMessageId = null,
        ?int $currentSceneId = null,
    ): array {
        $all = CharacterDiaryEntry::query()
            ->where('character_id', $npc->id)
            ->orderBy('created_at')
            ->get();

        $stats = [
            'total_entries' => $all->count(),
            'stale' => $all->where('is_stale', true)->count(),
            'filtered_by_recent' => 0,
            'kept_by_current_scene' => 0,
            'in_pool' => 0,
            'selected' => 0,
        ];

        $active = $all->where('is_stale', false)->values();
        if ($active->isEmpty()) {
            return ['selected' => collect(), 'stats' => $stats];
        }

        $pool = $this->buildPool($active, $recentFirstMessageId, $currentSceneId);
        $stats['filtered_by_recent'] = $active->count() - $pool->count();
        $stats['in_pool'] = $pool->count();
        $stats['kept_by_current_scene'] = $this->countKeptByCurrentScene(
            $pool,
            $recentFirstMessageId,
            $currentSceneId,
        );

        if ($pool->isEmpty()) {
            return ['selected' => collect(), 'stats' => $stats];
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

        $selected = $result->take($limit)->values();
        $stats['selected'] = $selected->count();

        return ['selected' => $selected, 'stats' => $stats];
    }

    /**
     * Формула блендинга similarity с importance.
     * importance = null → boost = 1.0 (старые записи работают как раньше).
     * importance = 1.0 → boost = 1.0 (не может быть выше).
     * importance = 0.0 → boost = 0.7 (штраф за неважное).
     */
    public static function blendScore(float $similarity, ?float $importance): float
    {
        if ($importance === null) {
            return $similarity;
        }

        $importance = max(0.0, min(1.0, $importance));

        return $similarity * (0.7 + 0.3 * $importance);
    }

    /**
     * @param  Collection<int, CharacterDiaryEntry>  $all
     * @return Collection<int, CharacterDiaryEntry>
     */
    private function buildPool(
        Collection $all,
        ?int $recentFirstMessageId,
        ?int $currentSceneId,
    ): Collection {
        if ($recentFirstMessageId === null) {
            return $all->values();
        }

        return $all
            ->filter(function (CharacterDiaryEntry $entry) use ($recentFirstMessageId, $currentSceneId): bool {
                $isCurrentScene = $currentSceneId !== null
                    && $entry->scene_id !== null
                    && (int) $entry->scene_id === $currentSceneId;

                if ($isCurrentScene) {
                    return true;
                }

                $from = (int) ($entry->from_message_id ?? 0);

                return $from < $recentFirstMessageId;
            })
            ->values();
    }

    /**
     * @param  Collection<int, CharacterDiaryEntry>  $pool
     */
    private function countKeptByCurrentScene(
        Collection $pool,
        ?int $recentFirstMessageId,
        ?int $currentSceneId,
    ): int {
        if ($recentFirstMessageId === null || $currentSceneId === null) {
            return 0;
        }

        return $pool
            ->filter(function (CharacterDiaryEntry $entry) use ($recentFirstMessageId, $currentSceneId): bool {
                return $entry->scene_id !== null
                    && (int) $entry->scene_id === $currentSceneId
                    && (int) ($entry->from_message_id ?? 0) >= $recentFirstMessageId;
            })
            ->count();
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
            'SELECT id,
                    1 - (embedding <=> ?::vector) AS similarity,
                    importance
             FROM character_diary_entries
             WHERE id = ANY(?::bigint[])
             ORDER BY embedding <=> ?::vector
             LIMIT ?',
            [$pgVector, $pgArray, $pgVector, self::CANDIDATE_LIMIT],
        );

        $scored = [];
        foreach ($rows as $row) {
            $sim = (float) $row->similarity;
            if ($sim < self::MIN_SIMILARITY) {
                break;
            }

            $importance = $row->importance === null ? null : (float) $row->importance;
            $scored[] = [
                'id' => (int) $row->id,
                'final' => self::blendScore($sim, $importance),
            ];
        }

        if ($scored === []) {
            return collect();
        }

        usort($scored, fn (array $a, array $b): int => $b['final'] <=> $a['final']);

        $ids = array_slice(array_column($scored, 'id'), 0, $limit);

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
