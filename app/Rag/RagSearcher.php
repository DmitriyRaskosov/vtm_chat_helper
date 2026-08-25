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
     * @return Collection<int, RagChunk>
     */
    public function search(string $query, int $limit = 5, ?array $types = null): Collection
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
            ->limit($limit)
            ->get();
    }
}
