<?php

namespace App\Character;

use App\Models\CharacterBioChunk;
use App\Rag\EmbeddingProvider;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Pgvector\Laravel\Distance;

class CharacterBioSearcher
{
    public const DEFAULT_MAX_DISTANCE = 0.5;

    public function __construct(private EmbeddingProvider $embeddings) {}

    /**
     * Nearest neighbors among chunks that already belong to this character.
     *
     * @return Collection<int, CharacterBioChunk>
     */
    public function search(int $characterId, string $query, int $limit = 5, float $maxDistance = self::DEFAULT_MAX_DISTANCE): Collection
    {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException('A biography search query is required.');
        }

        $vectorHits = CharacterBioChunk::query()
            ->where('character_id', $characterId)
            ->nearestNeighbors('embedding', $this->embeddings->embed($query), Distance::Cosine)
            ->limit($limit)
            ->get()
            ->filter(function (CharacterBioChunk $chunk) use ($maxDistance): bool {
                $distance = $chunk->neighbor_distance;

                return $distance === null || (float) $distance <= $maxDistance;
            });

        $ftsHits = CharacterBioChunk::query()
            ->where('character_id', $characterId)
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$query])
            ->limit($limit)
            ->get();

        return $vectorHits
            ->concat($ftsHits)
            ->unique('id')
            ->take($limit)
            ->values();
    }
}
