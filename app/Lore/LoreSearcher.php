<?php

namespace App\Lore;

use App\Character\CharacterLoreKnowledgeService;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Models\Character;
use App\Models\LoreChunk;
use App\Rag\EmbeddingProvider;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Pgvector\Laravel\Distance;

class LoreSearcher
{
    public const DEFAULT_MAX_DISTANCE = 0.5;

    public function __construct(
        private EmbeddingProvider $embeddings,
        private CharacterLoreKnowledgeService $knowledge,
    ) {}

    /**
     * Chronicle-scoped hybrid search. Pass `$knownLoreEntryIds` to apply an
     * NPC knowledge grant filter; `null` means no knowledge restriction.
     *
     * @param  list<int>|null  $knownLoreEntryIds
     * @return Collection<int, LoreChunk>
     */
    public function search(
        int $chronicleId,
        string $query,
        int $limit = 5,
        bool $includeStorytellerOnly = true,
        ?array $knownLoreEntryIds = null,
        float $maxDistance = self::DEFAULT_MAX_DISTANCE,
    ): Collection {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException('A lore search query is required.');
        }

        if ($knownLoreEntryIds !== null && $knownLoreEntryIds === []) {
            return new Collection;
        }

        $vectorHits = LoreChunk::query()
            ->where('chronicle_id', $chronicleId)
            ->whereHas(
                'loreEntry',
                fn ($builder) => $builder->where('status', '!=', LoreEntryStatus::Archived),
            )
            ->when(! $includeStorytellerOnly, fn ($builder) => $builder->where('visibility', LoreVisibility::Public))
            ->when($knownLoreEntryIds !== null, fn ($builder) => $builder->whereIn('lore_entry_id', $knownLoreEntryIds))
            ->nearestNeighbors('embedding', $this->embeddings->embed($query), Distance::Cosine)
            ->limit($limit)
            ->get()
            ->filter(function (LoreChunk $chunk) use ($maxDistance): bool {
                $distance = $chunk->neighbor_distance;

                return $distance === null || (float) $distance <= $maxDistance;
            });

        $ftsHits = LoreChunk::query()
            ->where('chronicle_id', $chronicleId)
            ->whereHas(
                'loreEntry',
                fn ($builder) => $builder->where('status', '!=', LoreEntryStatus::Archived),
            )
            ->when(! $includeStorytellerOnly, fn ($builder) => $builder->where('visibility', LoreVisibility::Public))
            ->when($knownLoreEntryIds !== null, fn ($builder) => $builder->whereIn('lore_entry_id', $knownLoreEntryIds))
            ->whereRaw("search_vector @@ plainto_tsquery('simple', ?)", [$query])
            ->limit($limit)
            ->get();

        return $vectorHits
            ->concat($ftsHits)
            ->unique('id')
            ->take($limit)
            ->values();
    }

    /**
     * NPC-facing search: only explicitly granted lore, including storyteller_only
     * entries the character was granted. Public visibility is not automatic knowledge.
     *
     * @return Collection<int, LoreChunk>
     */
    public function searchForCharacter(
        Character $character,
        string $query,
        int $limit = 5,
        float $maxDistance = self::DEFAULT_MAX_DISTANCE,
    ): Collection {
        return $this->search(
            (int) $character->chronicle_id,
            $query,
            $limit,
            includeStorytellerOnly: true,
            knownLoreEntryIds: $this->knowledge->knownLoreEntryIds($character),
            maxDistance: $maxDistance,
        );
    }
}
