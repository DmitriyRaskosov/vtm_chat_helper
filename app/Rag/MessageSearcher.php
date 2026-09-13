<?php

namespace App\Rag;

use App\Models\MessageEmbedding;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Pgvector\Laravel\Distance;

class MessageSearcher
{
    public const DEFAULT_MAX_DISTANCE = 0.5;

    public function __construct(private EmbeddingProvider $embeddings) {}

    /**
     * Chronicle-scoped search over the dedicated message corpus.
     *
     * @return Collection<int, MessageEmbedding>
     */
    public function search(
        int $chronicleId,
        string $query,
        int $limit = 5,
        ?int $gameSessionId = null,
        ?int $sceneId = null,
        float $maxDistance = self::DEFAULT_MAX_DISTANCE,
    ): Collection {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException('A message search query is required.');
        }

        $vector = $this->embeddings->embed($query);

        $primary = MessageEmbedding::query()
            ->where('chronicle_id', $chronicleId)
            ->when($gameSessionId !== null, fn ($builder) => $builder->where('game_session_id', $gameSessionId))
            ->when($sceneId !== null, fn ($builder) => $builder->where('scene_id', $sceneId))
            ->nearestNeighbors('embedding', $vector, Distance::Cosine)
            ->limit($limit)
            ->get()
            ->filter(function (MessageEmbedding $row) use ($maxDistance): bool {
                $distance = $row->neighbor_distance;

                return $distance === null || (float) $distance <= $maxDistance;
            });

        $fts = MessageEmbedding::query()
            ->where('chronicle_id', $chronicleId)
            ->when($gameSessionId !== null, fn ($builder) => $builder->where('game_session_id', $gameSessionId))
            ->when($sceneId !== null, fn ($builder) => $builder->where('scene_id', $sceneId))
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$query])
            ->limit($limit)
            ->get();

        $merged = $primary->concat($fts)->unique('message_id');

        return $merged
            ->sortBy(fn (MessageEmbedding $row): float => (float) ($row->neighbor_distance ?? 0))
            ->take($limit)
            ->values();
    }
}
