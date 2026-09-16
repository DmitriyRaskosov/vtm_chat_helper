<?php

namespace App\Retrieval\Hybrid;

use App\Character\CharacterBioSearcher;
use App\Context\TokenEstimator;
use App\Enums\RetrievalCorpus;
use App\Lore\LoreSearcher;
use App\Memory\CharacterMemorySearcher;
use App\Memory\MemoryBridgeService;
use App\Models\Character;
use App\Models\CharacterBioChunk;
use App\Models\CharacterMemoryNode;
use App\Models\GameRuleChunk;
use App\Models\LoreChunk;
use App\Models\MessageEmbedding;
use App\Models\WorldEntity;
use App\Rag\MessageSearcher;
use App\Rulebook\RuleSearcher;
use App\World\WorldRelationService;
use InvalidArgumentException;

class HybridRetrievalCoordinator
{
    public function __construct(
        private MessageSearcher $messages,
        private CharacterBioSearcher $bio,
        private CharacterMemorySearcher $memory,
        private LoreSearcher $lore,
        private RuleSearcher $rules,
        private WorldRelationService $relations,
        private MemoryBridgeService $bridges,
        private TokenEstimator $tokens,
    ) {}

    public function retrieve(RetrievalRequest $request): RetrievalBundle
    {
        $query = trim($request->query);

        if ($query === '') {
            throw new InvalidArgumentException('A retrieval query is required.');
        }

        $limit = $request->perCorpusLimit ?? (int) config('retrieval.hybrid.per_corpus_limit', 5);
        $maxHits = (int) config('retrieval.hybrid.max_hits', 20);
        $character = $request->characterId !== null
            ? Character::query()->findOrFail($request->characterId)
            : null;

        if ($character !== null && (int) $character->chronicle_id !== $request->chronicleId) {
            throw new InvalidArgumentException('Character does not belong to the retrieval chronicle.');
        }

        $hits = [];

        if ($request->includeMessages) {
            $hits = [...$hits, ...$this->fromMessages($request, $query, $limit)];
        }

        if ($request->includeBio && $character !== null) {
            $hits = [...$hits, ...$this->fromBio($request, $character, $query, $limit)];
        }

        if ($request->includeMemory && $character !== null) {
            $hits = [...$hits, ...$this->fromMemory($request, $character, $query, $limit)];
        }

        if ($request->includeLore) {
            $hits = [...$hits, ...$this->fromLore($request, $character, $query, $limit)];
        }

        if ($request->includeRules && ($request->rulesetId !== null || ($request->edition !== null && $request->edition !== ''))) {
            $hits = [...$hits, ...$this->fromRules($request, $character, $query, $limit)];
        }

        if ($request->includeRelations && $character !== null) {
            $hits = [...$hits, ...$this->fromRelations($request, $character)];
        }

        return new RetrievalBundle($this->deduplicate($hits, $maxHits));
    }

    /**
     * @return list<RetrievalHit>
     */
    private function fromMessages(RetrievalRequest $request, string $query, int $limit): array
    {
        $rows = $this->messages->search(
            $request->chronicleId,
            $query,
            $limit,
            $request->gameSessionId,
            $request->sceneId,
        );

        return $rows->values()->map(function (MessageEmbedding $row, int $index) use ($request): RetrievalHit {
            return new RetrievalHit(
                RetrievalCorpus::Message,
                'message',
                (int) $row->message_id,
                (string) $row->content,
                $this->scores($row->neighbor_distance, $index),
                'vector+fts message_embeddings with chronicle/session scope',
                array_filter([
                    'chronicle_id' => $request->chronicleId,
                    'game_session_id' => $request->gameSessionId,
                    'scene_id' => $request->sceneId,
                ], fn ($value) => $value !== null),
                (int) ($row->token_estimate ?: $this->tokens->estimate((string) $row->content)),
                ['table' => 'message_embeddings', 'message_id' => (int) $row->message_id],
                messageId: (int) $row->message_id,
            );
        })->all();
    }

    /**
     * @return list<RetrievalHit>
     */
    private function fromBio(RetrievalRequest $request, Character $character, string $query, int $limit): array
    {
        return $this->bio->search($character->id, $query, $limit)
            ->values()
            ->map(function (CharacterBioChunk $chunk, int $index) use ($request, $character): RetrievalHit {
                return new RetrievalHit(
                    RetrievalCorpus::Bio,
                    'character_bio_chunk',
                    (int) $chunk->id,
                    (string) $chunk->content,
                    $this->scores($chunk->neighbor_distance, $index),
                    'vector+fts character_bio_chunks scoped to character',
                    ['chronicle_id' => $request->chronicleId, 'character_id' => $character->id],
                    (int) $chunk->token_estimate,
                    [
                        'table' => 'character_bio_chunks',
                        'character_id' => $character->id,
                        'biography_version_id' => $chunk->biography_version_id,
                        'section' => $chunk->section->value,
                    ],
                    documentId: (int) $chunk->biography_version_id,
                );
            })
            ->all();
    }

    /**
     * @return list<RetrievalHit>
     */
    private function fromMemory(RetrievalRequest $request, Character $character, string $query, int $limit): array
    {
        return $this->memory->search($character->id, $query, $limit)
            ->values()
            ->map(function (CharacterMemoryNode $node, int $index) use ($request, $character): RetrievalHit {
                $messageIds = $this->bridges->messagesFor($node)->pluck('id')->map(fn ($id): int => (int) $id)->all();

                return new RetrievalHit(
                    RetrievalCorpus::Memory,
                    'character_memory_node',
                    (int) $node->id,
                    (string) $node->node_text,
                    $this->scores($node->neighbor_distance, $index, (int) $node->importance),
                    'vector+fts+alias character_memory_nodes scoped to character',
                    ['chronicle_id' => $request->chronicleId, 'character_id' => $character->id],
                    $this->tokens->estimate((string) $node->node_text),
                    [
                        'table' => 'character_memory_nodes',
                        'character_id' => $character->id,
                        'message_ids' => $messageIds,
                        'is_false_belief' => $node->is_false_belief,
                    ],
                    messageId: $messageIds[0] ?? null,
                );
            })
            ->all();
    }

    /**
     * @return list<RetrievalHit>
     */
    private function fromLore(RetrievalRequest $request, ?Character $character, string $query, int $limit): array
    {
        $chunks = $request->asNpc && $character !== null
            ? $this->lore->searchForCharacter($character, $query, $limit)
            : $this->lore->search($request->chronicleId, $query, $limit, includeStorytellerOnly: ! $request->asNpc);

        return $chunks->values()->map(function (LoreChunk $chunk, int $index) use ($request): RetrievalHit {
            return new RetrievalHit(
                RetrievalCorpus::Lore,
                'lore_chunk',
                (int) $chunk->id,
                (string) $chunk->content,
                $this->scores($chunk->neighbor_distance, $index),
                $request->asNpc
                    ? 'lore_chunks filtered by lore clearance'
                    : 'lore_chunks scoped to chronicle',
                array_filter([
                    'chronicle_id' => $request->chronicleId,
                    'character_id' => $request->characterId,
                    'as_npc' => $request->asNpc,
                ], fn ($value) => $value !== null && $value !== false),
                (int) $chunk->token_estimate,
                [
                    'table' => 'lore_chunks',
                    'lore_entry_id' => $chunk->lore_entry_id,
                    'visibility' => $chunk->visibility->value,
                ],
                documentId: (int) $chunk->lore_entry_id,
            );
        })->all();
    }

    /**
     * @return list<RetrievalHit>
     */
    private function fromRules(RetrievalRequest $request, ?Character $character, string $query, int $limit): array
    {
        $chunks = $request->asNpc && $character !== null
            ? $this->rules->searchForCharacter(
                $character,
                $query,
                $request->rulesetId,
                $request->edition,
                $limit,
            )
            : $this->rules->search(
                $query,
                $request->rulesetId,
                $request->edition,
                chronicleId: $request->chronicleId,
                limit: $limit,
            );

        return $chunks->values()->map(function (GameRuleChunk $chunk, int $index) use ($request): RetrievalHit {
            return new RetrievalHit(
                RetrievalCorpus::Rules,
                'game_rule_chunk',
                (int) $chunk->id,
                (string) $chunk->content,
                $this->scores($chunk->neighbor_distance, $index),
                $request->asNpc
                    ? 'game_rule_chunks filtered by character_rule_knowledge'
                    : 'game_rule_chunks with ruleset/edition then chronicle override',
                array_filter([
                    'chronicle_id' => $request->chronicleId,
                    'ruleset_id' => $request->rulesetId,
                    'edition' => $request->edition,
                    'character_id' => $request->characterId,
                ], fn ($value) => $value !== null),
                (int) $chunk->token_estimate,
                [
                    'table' => 'game_rule_chunks',
                    'rule_document_id' => $chunk->rule_document_id,
                    'source_reference' => $chunk->source_reference,
                ],
                documentId: (int) $chunk->rule_document_id,
            );
        })->all();
    }

    /**
     * Direct 1-hop world relations of the NPC. GraphRAG expansion is stage 30.
     *
     * @return list<RetrievalHit>
     */
    private function fromRelations(RetrievalRequest $request, Character $character): array
    {
        $entity = WorldEntity::query()->find($character->id);

        if ($entity === null) {
            return [];
        }

        return $this->relations->neighbors($entity)
            ->values()
            ->map(function ($relation, int $index) use ($request, $character, $entity): RetrievalHit {
                $other = $relation->other($entity);
                $note = $relation->note !== null && $relation->note !== '' ? ': '.$relation->note : '';
                $content = $entity->canonical_name.' '.$relation->relation.' '.$other->canonical_name.$note;

                return new RetrievalHit(
                    RetrievalCorpus::Relation,
                    'world_relation',
                    (int) $relation->id,
                    $content,
                    ['rank' => 1 / ($index + 1), 'weight' => (float) $relation->weight],
                    'direct world_relations neighbors, no GraphRAG expansion',
                    ['chronicle_id' => $request->chronicleId, 'character_id' => $character->id],
                    $this->tokens->estimate($content),
                    [
                        'table' => 'world_relations',
                        'relation_type' => $relation->relation,
                        'source_id' => $relation->source_id,
                        'target_id' => $relation->target_id,
                    ],
                );
            })
            ->all();
    }

    /**
     * @param  list<RetrievalHit>  $hits
     * @return list<RetrievalHit>
     */
    private function deduplicate(array $hits, int $maxHits): array
    {
        $bestByKey = [];
        $derivedMessageIds = [];

        foreach ($hits as $hit) {
            if ($hit->corpus === RetrievalCorpus::Memory) {
                foreach ($hit->provenance['message_ids'] ?? [] as $messageId) {
                    $derivedMessageIds[(int) $messageId] = true;
                }
            }
        }

        foreach ($hits as $hit) {
            if (
                $hit->corpus === RetrievalCorpus::Message
                && $hit->messageId !== null
                && isset($derivedMessageIds[$hit->messageId])
            ) {
                continue;
            }

            $key = $hit->documentId !== null
                ? $hit->corpus->value.':doc:'.$hit->documentId
                : $hit->corpus->value.':'.$hit->sourceType.':'.$hit->sourceId;

            if (! isset($bestByKey[$key]) || $hit->score() > $bestByKey[$key]->score()) {
                $bestByKey[$key] = $hit;
            }
        }

        $unique = array_values($bestByKey);
        usort($unique, fn (RetrievalHit $left, RetrievalHit $right): int => $right->score() <=> $left->score());

        return array_slice($unique, 0, $maxHits);
    }

    /**
     * @return array<string, float|int|null>
     */
    private function scores(mixed $neighborDistance, int $index, ?int $importance = null): array
    {
        $distance = $neighborDistance === null ? null : (float) $neighborDistance;

        return array_filter([
            'vector' => $distance === null ? null : round(1 - $distance, 4),
            'rank' => round(1 / ($index + 1), 4),
            'importance' => $importance,
        ], fn ($value) => $value !== null);
    }
}
