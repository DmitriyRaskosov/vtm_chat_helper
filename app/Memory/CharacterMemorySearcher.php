<?php

namespace App\Memory;

use App\Models\CharacterMemoryNode;
use App\Rag\EmbeddingProvider;
use App\World\AliasNormalizer;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Pgvector\Laravel\Distance;

class CharacterMemorySearcher
{
    public const DEFAULT_MAX_DISTANCE = 0.5;

    public function __construct(private EmbeddingProvider $embeddings) {}

    /**
     * Subjective memory search. Does not update recall metrics.
     *
     * @return Collection<int, CharacterMemoryNode>
     */
    public function search(
        int $characterId,
        string $query,
        int $limit = 5,
        float $maxDistance = self::DEFAULT_MAX_DISTANCE,
    ): Collection {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException('A memory search query is required.');
        }

        $normalizedQuery = AliasNormalizer::normalize($query);

        $exact = CharacterMemoryNode::query()
            ->where('character_id', $characterId)
            ->where(function ($builder) use ($query, $normalizedQuery): void {
                $builder->whereRaw('lower(node_text) = lower(?)', [$query])
                    ->orWhereRaw('EXISTS (
                        SELECT 1 FROM jsonb_array_elements_text(aliases) alias
                        WHERE lower(alias) = lower(?)
                           OR lower(alias) = ?
                    )', [$query, $normalizedQuery]);
            })
            ->get();

        $vectorHits = CharacterMemoryNode::query()
            ->where('character_id', $characterId)
            ->nearestNeighbors('embedding', $this->embeddings->embed($query), Distance::Cosine)
            ->limit($limit)
            ->get()
            ->filter(function (CharacterMemoryNode $node) use ($maxDistance): bool {
                $distance = $node->neighbor_distance;

                return $distance === null || (float) $distance <= $maxDistance;
            });

        $ftsHits = CharacterMemoryNode::query()
            ->where('character_id', $characterId)
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$query])
            ->limit($limit)
            ->get();

        return $exact
            ->concat($vectorHits)
            ->concat($ftsHits)
            ->unique('id')
            ->sort(function (CharacterMemoryNode $left, CharacterMemoryNode $right) use ($query): int {
                return [
                    $this->isExact($left, $query) ? 0 : 1,
                    -$left->importance,
                    -$left->confidence,
                    -($left->updated_at?->getTimestamp() ?? 0),
                ] <=> [
                    $this->isExact($right, $query) ? 0 : 1,
                    -$right->importance,
                    -$right->confidence,
                    -($right->updated_at?->getTimestamp() ?? 0),
                ];
            })
            ->take($limit)
            ->values();
    }

    private function isExact(CharacterMemoryNode $node, string $query): bool
    {
        if (mb_strtolower($node->node_text) === mb_strtolower($query)) {
            return true;
        }

        foreach ($node->aliases ?? [] as $alias) {
            if (AliasNormalizer::normalize((string) $alias) === AliasNormalizer::normalize($query)) {
                return true;
            }
        }

        return false;
    }
}
