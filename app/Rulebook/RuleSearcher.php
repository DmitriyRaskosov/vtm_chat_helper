<?php

namespace App\Rulebook;

use App\Character\CharacterRuleKnowledgeService;
use App\Enums\RuleDocumentStatus;
use App\Models\Character;
use App\Models\GameRuleChunk;
use App\Rag\EmbeddingProvider;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;
use Pgvector\Laravel\Distance;

class RuleSearcher
{
    public const DEFAULT_MAX_DISTANCE = 0.5;

    public function __construct(
        private EmbeddingProvider $embeddings,
        private CharacterRuleKnowledgeService $knowledge,
    ) {}

    /**
     * Search base rules, then apply chronicle overrides. Never queries lore or bio corpora.
     *
     * @param  list<int>|null  $knownRuleDocumentIds
     * @return Collection<int, GameRuleChunk>
     */
    public function search(
        string $query,
        ?int $rulesetId = null,
        ?string $edition = null,
        ?int $chronicleId = null,
        int $limit = 5,
        ?array $knownRuleDocumentIds = null,
        float $maxDistance = self::DEFAULT_MAX_DISTANCE,
    ): Collection {
        $query = trim($query);

        if ($query === '') {
            throw new InvalidArgumentException('A rule search query is required.');
        }

        if ($rulesetId === null && ($edition === null || trim($edition) === '')) {
            throw new InvalidArgumentException('Rule search requires a ruleset or edition.');
        }

        if ($knownRuleDocumentIds !== null && $knownRuleDocumentIds === []) {
            return new Collection;
        }

        $scoped = function (Builder $builder) use ($rulesetId, $edition, $chronicleId, $knownRuleDocumentIds): void {
            $builder
                ->whereHas(
                    'document',
                    fn ($query) => $query->where('status', '!=', RuleDocumentStatus::Archived),
                )
                ->when($rulesetId !== null, fn ($query) => $query->where('ruleset_id', $rulesetId))
                ->when($edition !== null && $edition !== '', fn ($query) => $query->where('edition', $edition))
                ->when($knownRuleDocumentIds !== null, fn ($query) => $query->whereIn('rule_document_id', $knownRuleDocumentIds));

            if ($chronicleId === null) {
                $builder->whereNull('chronicle_id');

                return;
            }

            $builder->where(function (Builder $inner) use ($chronicleId): void {
                $inner->where(function (Builder $override) use ($chronicleId): void {
                    $override->where('chronicle_id', $chronicleId)
                        ->whereNotNull('chronicle_rule_override_id');
                })->orWhere(function (Builder $base) use ($chronicleId): void {
                    $base->whereNull('chronicle_id')
                        ->whereNotExists(function ($sub) use ($chronicleId): void {
                            $sub->selectRaw('1')
                                ->from('chronicle_rule_overrides')
                                ->whereColumn('chronicle_rule_overrides.rule_document_id', 'game_rule_chunks.rule_document_id')
                                ->where('chronicle_rule_overrides.chronicle_id', $chronicleId)
                                ->where('chronicle_rule_overrides.status', RuleDocumentStatus::Approved->value);
                        });
                });
            });
        };

        $vectorHits = GameRuleChunk::query()
            ->tap($scoped)
            ->nearestNeighbors('embedding', $this->embeddings->embed($query), Distance::Cosine)
            ->limit($limit)
            ->get()
            ->filter(function (GameRuleChunk $chunk) use ($maxDistance): bool {
                $distance = $chunk->neighbor_distance;

                return $distance === null || (float) $distance <= $maxDistance;
            });

        $ftsHits = GameRuleChunk::query()
            ->tap($scoped)
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
     * @return Collection<int, GameRuleChunk>
     */
    public function searchForCharacter(
        Character $character,
        string $query,
        ?int $rulesetId = null,
        ?string $edition = null,
        int $limit = 5,
        float $maxDistance = self::DEFAULT_MAX_DISTANCE,
    ): Collection {
        return $this->search(
            $query,
            $rulesetId,
            $edition,
            chronicleId: (int) $character->chronicle_id,
            limit: $limit,
            knownRuleDocumentIds: $this->knowledge->knownRuleDocumentIds($character),
            maxDistance: $maxDistance,
        );
    }
}
