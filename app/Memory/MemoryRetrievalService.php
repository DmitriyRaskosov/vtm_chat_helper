<?php

namespace App\Memory;

use App\Models\Character;
use App\Models\CharacterMemory;
use App\Rag\EmbeddingProvider;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemoryRetrievalService
{
    private const CANDIDATE_LIMIT = 40;
    private const MIN_SIMILARITY = 0.05;

    public function __construct(
        private EmbeddingProvider $embeddings,
    ) {}

    /**
     * @return Collection<int, CharacterMemory>
     */
    public function retrieve(Character $npc, string $query, int $limit = 6): Collection
    {
        $query = trim($query);
        if ($query === '') {
            return collect();
        }

        $vector = $this->embeddings->embed($query);
        $pgVector = $this->formatVector($vector);

        $rows = DB::select(
            'SELECT id, type, content, importance, access_count, last_accessed_at, created_at,
                    1 - (embedding <=> ?::vector) AS similarity
             FROM character_memories
             WHERE character_id = ?
               AND chronicle_id = ?
             ORDER BY embedding <=> ?::vector
             LIMIT ?',
            [$pgVector, $npc->id, $npc->chronicle_id, $pgVector, self::CANDIDATE_LIMIT],
        );

        if (app()->environment('local')) {
            $topSims = array_map(
                fn ($r) => ['id' => (int) $r->id, 'sim' => round((float) $r->similarity, 4)],
                array_slice($rows, 0, 10),
            );
            \Log::info('memory.retrieve.similarity', [
                'query' => $query,
                'keywords' => $this->extractKeywords($query),
                'top_10_by_cosine' => $topSims,
            ]);
        }
    
        if ($rows === []) {
            return collect();
        }

        $now = now();
        $scored = [];

        foreach ($rows as $row) {
            $similarity = (float) $row->similarity;
            if ($similarity < self::MIN_SIMILARITY) {
                continue;
            }

            $importance = (int) $row->importance;
            $createdAt = Carbon::parse($row->created_at);
            $days = max(0, $createdAt->diffInDays($now));
            $recency = exp(-$days / 30);

            $lastAccessed = $row->last_accessed_at !== null
                ? Carbon::parse($row->last_accessed_at)
                : null;
            $recentlyShown = $lastAccessed !== null && $lastAccessed->diffInMinutes($now) < 5;

            // Keyword boost: how many significant words from query appear in content
            $queryWords = $this->extractKeywords($query);
            $contentLower = mb_strtolower($row->content);
            $hits = 0;
            foreach ($queryWords as $word) {
                if (mb_strpos($contentLower, $word) !== false) {
                    $hits++;
                }
            }
            $keywordBoost = $queryWords === [] ? 0 : ($hits / count($queryWords));

            $score = 0.65 * $similarity // Semantic — главный сигнал.
                + 0.20 * ($importance / 10)
                + 0.10 * $recency
                + 0.05 * $keywordBoost;  // Keyword — подстраховка.

            if ($recentlyShown) {
                $score *= 0.7;
            }

            $scored[] = [
                'id' => (int) $row->id,
                'type' => (string) $row->type,
                'content' => (string) $row->content,
                'importance' => $importance,
                'score' => $score,
            ];
        }

        if ($scored === []) {
            return collect();
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        // Dedup exact-content
        $deduped = [];
        $seen = [];
        foreach ($scored as $row) {
            $key = mb_strtolower($row['content']);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $deduped[] = $row;
        }

        // Мягкий diversity penalty: понижаем score за каждый повтор типа.
        // Первые 2 записи типа — без штрафа. Дальше — 0.8, 0.64, 0.51...
        $typeSeen = [];
        foreach ($deduped as &$row) {
            $type = $row['type'];
            $count = $typeSeen[$type] ?? 0;
            if ($count >= 2) {
                $row['score'] *= 0.8 ** ($count - 1);
            }
            $typeSeen[$type] = $count + 1;
        }
        unset($row);

        usort($deduped, fn ($a, $b) => $b['score'] <=> $a['score']);

        $final = array_slice($deduped, 0, $limit);

        if ($final === []) {
            return collect();
        }

        $ids = array_column($final, 'id');

        DB::table('character_memories')
            ->whereIn('id', $ids)
            ->update([
                'access_count' => DB::raw('access_count + 1'),
                'last_accessed_at' => $now,
            ]);

        $models = CharacterMemory::query()->whereIn('id', $ids)->get()->keyBy('id');

        return collect($ids)->map(fn (int $id) => $models->get($id))->filter()->values();
    }

    /**
     * @return list<string>
     */
    private function extractKeywords(string $query): array
    {
        $stopWords = ['что', 'как', 'это', 'его', 'она', 'они', 'мне', 'тебе', 'был', 'была',
            'есть', 'быть', 'для', 'про', 'при', 'над', 'под', 'без', 'чем', 'тем',
            'помнишь', 'говорил', 'сказал', 'можешь', 'расскажи'];

        $words = preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($query), -1, PREG_SPLIT_NO_EMPTY);
        $words = array_filter($words, fn (string $w): bool =>
            mb_strlen($w) >= 3 && ! in_array($w, $stopWords, true)
        );

        return array_values(array_unique($words));
    }

    /**
     * @param  list<float>  $vector
     */
    private function formatVector(array $vector): string
    {
        return '['.implode(',', array_map(fn (float $v): string => (string) $v, $vector)).']';
    }
}