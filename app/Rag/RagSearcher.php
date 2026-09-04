<?php

namespace App\Rag;

use App\Enums\RagSourceType;
use App\Models\RagChunk;
use Illuminate\Support\Collection;
use Pgvector\Laravel\Distance;

class RagSearcher
{
    public function __construct(private EmbeddingProvider $embeddings) {}

    /**
     * @param  list<RagSourceType>|null  $types
     * @param  array{game_session_id?: int, scene_id?: int}  $filters
     * @return Collection<int, RagChunk>
     */
    public function search(string $query, int $limit = 5, ?array $types = null, array $filters = []): Collection
    {
        $vector = $this->embeddings->embed($query);

        return RagChunk::query()
            ->nearestNeighbors('embedding', $vector, Distance::Cosine)
            ->when($types !== null && $types !== [], function ($builder) use ($types) {
                $builder->whereIn('source_type', array_map(
                    fn (RagSourceType $type): string => $type->value,
                    $types,
                ));
            })
            ->when(isset($filters['game_session_id']), function ($builder) use ($filters) {
                $builder->where('metadata->game_session_id', (int) $filters['game_session_id']);
            })
            ->when(isset($filters['scene_id']), function ($builder) use ($filters) {
                $builder->where('metadata->scene_id', (int) $filters['scene_id']);
            })
            ->limit($limit)
            ->get();
    }
}
