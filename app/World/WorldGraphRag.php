<?php

namespace App\World;

use App\Character\CharacterLoreKnowledgeService;
use App\Enums\LoreEntryStatus;
use App\Enums\WorldEntityStatus;
use App\Enums\WorldEventStatus;
use App\Enums\WorldEventVisibility;
use App\Lore\LoreSearcher;
use App\Memory\CharacterMemorySearcher;
use App\Memory\MemoryBridgeService;
use App\Models\Character;
use App\Models\CharacterAffiliation;
use App\Models\CharacterRelationship;
use App\Models\Chronicle;
use App\Models\LoreChunk;
use App\Models\LoreEntry;
use App\Models\LoreEntryEntity;
use App\Models\Scene;
use App\Models\WorldEntity;
use App\Models\WorldEvent;
use App\Models\WorldRelation;
use App\Retrieval\CteGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorldGraphRag
{
    public function __construct(
        private WorldEntityService $entities,
        private MemoryBridgeService $bridges,
        private CharacterLoreKnowledgeService $loreKnowledge,
        private LoreSearcher $lore,
        private CharacterMemorySearcher $memory,
    ) {}

    public function expandForNpc(Character $npc, string $query, ?Scene $scene = null): WorldGraphBundle
    {
        $npc->loadMissing('chronicle');
        $loreHits = $this->lore->searchForCharacter($npc, $query);
        $memoryHits = $this->memory->search($npc->id, $query);
        $seeds = $this->collectSeeds($npc->chronicle, $npc, $scene, [$query], $loreHits, $memoryHits);

        return $this->expand($npc->chronicle, $seeds, $npc);
    }

    /**
     * @param  list<string>  $aliasQueries
     * @param  Collection<int, mixed>  $loreChunks
     * @param  Collection<int, mixed>  $memoryNodes
     * @return list<int>
     */
    public function collectSeeds(
        Chronicle $chronicle,
        ?Character $npc = null,
        ?Scene $scene = null,
        array $aliasQueries = [],
        ?Collection $loreChunks = null,
        ?Collection $memoryNodes = null,
    ): array {
        $ids = [];

        if ($npc !== null && (int) $npc->chronicle_id === (int) $chronicle->id) {
            $ids[] = (int) $npc->id;
        }

        if ($scene !== null) {
            $scene->loadMissing('gameSession');
            if ((int) $scene->gameSession->chronicle_id === (int) $chronicle->id) {
                $eventIds = WorldEvent::query()
                    ->where('chronicle_id', $chronicle->id)
                    ->where('scene_id', $scene->id)
                    ->pluck('id');
                foreach ($eventIds as $eventId) {
                    $ids[] = (int) $eventId;
                }
            }
        }

        foreach ($aliasQueries as $alias) {
            $alias = trim($alias);
            if ($alias === '') {
                continue;
            }
            $match = $this->entities->findByAlias($chronicle, $alias);
            if ($match !== null) {
                $ids[] = (int) $match->id;
            }
        }

        foreach ($loreChunks ?? [] as $chunk) {
            $entryId = (int) ($chunk->lore_entry_id ?? 0);
            if ($entryId === 0) {
                continue;
            }
            $linked = LoreEntryEntity::query()
                ->where('chronicle_id', $chronicle->id)
                ->where('lore_entry_id', $entryId)
                ->pluck('entity_id');
            foreach ($linked as $entityId) {
                $ids[] = (int) $entityId;
            }
        }

        foreach ($memoryNodes ?? [] as $node) {
            foreach ($this->bridges->worldEntitiesFor($node) as $entity) {
                if ((int) $entity->chronicle_id === (int) $chronicle->id) {
                    $ids[] = (int) $entity->id;
                }
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  list<int>  $seedEntityIds
     * @param  list<string>  $relationTypeKeys
     */
    public function expand(
        Chronicle $chronicle,
        array $seedEntityIds,
        ?Character $asNpc = null,
        array $relationTypeKeys = [],
    ): WorldGraphBundle {
        $maxDepth = (int) config('retrieval.world_graphrag.max_depth', 2);
        $minAbsWeight = (float) config('retrieval.world_graphrag.min_abs_weight', 0.0);
        $maxNodes = (int) config('retrieval.world_graphrag.max_nodes', 32);
        $maxEdges = (int) config('retrieval.world_graphrag.max_edges', 64);
        $depthDecay = (float) config('retrieval.world_graphrag.depth_decay', 0.6);

        $seedEntityIds = array_values(array_unique(array_map('intval', $seedEntityIds)));
        $seedEntityIds = WorldEntity::query()
            ->where('chronicle_id', $chronicle->id)
            ->where('status', WorldEntityStatus::Active)
            ->whereIn('id', $seedEntityIds)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($seedEntityIds === []) {
            return new WorldGraphBundle((int) $chronicle->id, [], [], [], [], [], [], []);
        }

        $typeFilter = '';
        if ($relationTypeKeys !== []) {
            $keys = array_map(fn (string $key): string => "'".str_replace("'", "''", $key)."'", $relationTypeKeys);
            $typeFilter = 'AND t.key IN ('.implode(',', $keys).')';
        }

        $secretEventFilter = $asNpc !== null
            ? "AND NOT EXISTS (
                SELECT 1 FROM world_events secret
                WHERE secret.id = (CASE WHEN r.source_entity_id = w.entity_id THEN r.target_entity_id ELSE r.source_entity_id END)
                  AND secret.visibility = '".WorldEventVisibility::StorytellerOnly->value."'
              )"
            : '';

        $seedList = implode(',', $seedEntityIds);
        $walkLimit = max($maxNodes * 6, 64);
        $observedAt = now();
        $started = hrtime(true);

        $rows = CteGuard::run(fn () => DB::select(
            "
            WITH RECURSIVE walk AS (
                SELECT
                    e.id AS entity_id,
                    0 AS depth,
                    ARRAY[e.id]::bigint[] AS path,
                    NULL::bigint AS via_relation_id,
                    1.0::double precision AS path_strength
                FROM world_entities e
                WHERE e.chronicle_id = ?
                  AND e.status = '".WorldEntityStatus::Active->value."'
                  AND e.id IN ({$seedList})
                UNION ALL
                SELECT
                    CASE WHEN r.source_entity_id = w.entity_id THEN r.target_entity_id ELSE r.source_entity_id END,
                    w.depth + 1,
                    w.path || (CASE WHEN r.source_entity_id = w.entity_id THEN r.target_entity_id ELSE r.source_entity_id END),
                    r.id,
                    w.path_strength * ABS(r.weight::double precision)
                FROM walk w
                INNER JOIN world_relations r
                    ON r.chronicle_id = ?
                   AND r.ended_at IS NULL
                   AND (r.started_at IS NULL OR r.started_at <= ?)
                   AND ABS(r.weight) >= ?
                INNER JOIN world_relation_types t
                    ON t.id = r.relation_type_id
                   AND t.enabled = true
                   {$typeFilter}
                   AND (
                        r.source_entity_id = w.entity_id
                        OR (r.target_entity_id = w.entity_id AND (t.symmetric = true OR t.inverse_key IS NOT NULL))
                   )
                INNER JOIN world_entities nxt
                    ON nxt.id = (CASE WHEN r.source_entity_id = w.entity_id THEN r.target_entity_id ELSE r.source_entity_id END)
                   AND nxt.chronicle_id = ?
                   AND nxt.status = '".WorldEntityStatus::Active->value."'
                WHERE w.depth < ?
                  AND NOT (
                    CASE WHEN r.source_entity_id = w.entity_id THEN r.target_entity_id ELSE r.source_entity_id END
                  ) = ANY(w.path)
                  {$secretEventFilter}
            )
            SELECT entity_id, depth, path, via_relation_id, path_strength
            FROM walk
            LIMIT ?
            ",
            [(int) $chronicle->id, (int) $chronicle->id, $observedAt, $minAbsWeight, (int) $chronicle->id, $maxDepth, $walkLimit],
        ));
        $cteMs = (int) round((hrtime(true) - $started) / 1_000_000);

        $bestByEntity = [];
        $relationIds = [];
        $relationDepth = [];

        foreach ($rows as $row) {
            $entityId = (int) $row->entity_id;
            $depth = (int) $row->depth;
            $score = ($depthDecay ** $depth) * (float) $row->path_strength;

            if (! isset($bestByEntity[$entityId]) || $score > $bestByEntity[$entityId]['score']) {
                $bestByEntity[$entityId] = [
                    'id' => $entityId,
                    'depth' => $depth,
                    'score' => $score,
                    'seed' => in_array($entityId, $seedEntityIds, true),
                ];
            }

            if ($row->via_relation_id !== null) {
                $relationId = (int) $row->via_relation_id;
                $relationIds[$relationId] = $relationId;
                $relationDepth[$relationId] = min($relationDepth[$relationId] ?? $depth, $depth);
            }
        }

        uasort($bestByEntity, function (array $left, array $right): int {
            return [$right['score'], $left['id']] <=> [$left['score'], $right['id']];
        });
        $bestByEntity = array_slice($bestByEntity, 0, $maxNodes, true);
        $keptIds = array_map('intval', array_keys($bestByEntity));

        $models = WorldEntity::query()
            ->where('chronicle_id', $chronicle->id)
            ->whereIn('id', $keptIds)
            ->get()
            ->keyBy('id');

        $bundleEntities = [];
        foreach ($bestByEntity as $entityId => $meta) {
            $model = $models->get($entityId);
            if ($model === null) {
                continue;
            }

            $bundleEntities[] = new WorldGraphEntity(
                (int) $model->id,
                (string) $model->canonical_name,
                $model->entity_type->value,
                $model->short_description,
                $meta['depth'],
                $meta['seed'],
            );
        }

        $relations = WorldRelation::query()
            ->with('type')
            ->where('chronicle_id', $chronicle->id)
            ->whereIn('id', array_values($relationIds))
            ->whereIn('source_entity_id', $keptIds)
            ->whereIn('target_entity_id', $keptIds)
            ->orderBy('id')
            ->get()
            ->take($maxEdges);

        $bundleRelations = $relations->map(function (WorldRelation $relation) use ($relationDepth): WorldGraphRelation {
            return new WorldGraphRelation(
                (int) $relation->id,
                (string) $relation->type->key,
                (int) $relation->source_entity_id,
                (int) $relation->target_entity_id,
                (float) $relation->weight,
                $relation->note,
                $relationDepth[(int) $relation->id] ?? 1,
            );
        })->all();

        $characterIds = $models
            ->filter(fn (WorldEntity $entity): bool => $entity->entity_type->value === 'character')
            ->keys()
            ->map(fn ($id): int => (int) $id)
            ->all();

        $affiliations = CharacterAffiliation::query()
            ->where('chronicle_id', $chronicle->id)
            ->whereIn('character_id', $characterIds)
            ->whereIn('target_entity_id', $keptIds)
            ->orderBy('id')
            ->get()
            ->map(fn (CharacterAffiliation $row): array => [
                'id' => (int) $row->id,
                'character_id' => (int) $row->character_id,
                'target_entity_id' => (int) $row->target_entity_id,
                'affiliation_type' => $row->affiliation_type->value,
                'stance' => $row->stance->value,
                'trust' => (int) $row->trust,
                'loyalty' => (int) $row->loyalty,
                'fear' => (int) $row->fear,
                'obligation' => (int) $row->obligation,
                'role' => $row->role,
                'rank' => $row->rank,
            ])
            ->all();

        $relationships = CharacterRelationship::query()
            ->where('chronicle_id', $chronicle->id)
            ->whereIn('source_character_id', $characterIds)
            ->whereIn('target_character_id', $characterIds)
            ->orderBy('id')
            ->get()
            ->map(fn (CharacterRelationship $row): array => [
                'id' => (int) $row->id,
                'source_character_id' => (int) $row->source_character_id,
                'target_character_id' => (int) $row->target_character_id,
            ])
            ->all();

        $events = WorldEvent::query()
            ->where('chronicle_id', $chronicle->id)
            ->whereIn('id', $keptIds)
            ->where('status', WorldEventStatus::Canonical)
            ->when(
                $asNpc !== null,
                fn ($query) => $query->where('visibility', WorldEventVisibility::Public),
            )
            ->orderBy('id')
            ->get()
            ->map(fn (WorldEvent $event): array => [
                'id' => (int) $event->id,
                'title' => $event->title,
                'event_type' => $event->event_type->value,
                'importance' => (int) $event->importance,
                'visibility' => $event->visibility->value,
            ])
            ->all();

        $knownLoreIds = $asNpc !== null ? $this->loreKnowledge->visibleLoreEntryIds($asNpc) : null;
        $attachedLoreIds = LoreEntryEntity::query()
            ->where('chronicle_id', $chronicle->id)
            ->whereIn('entity_id', $keptIds)
            ->pluck('lore_entry_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $activeLoreIds = $attachedLoreIds->isEmpty()
            ? $attachedLoreIds
            : LoreEntry::query()
                ->where('chronicle_id', $chronicle->id)
                ->whereIn('id', $attachedLoreIds->all())
                ->where('status', '!=', LoreEntryStatus::Archived)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values();

        $rejectedKnowledge = 0;
        $loreEntryIds = $activeLoreIds;
        if ($knownLoreIds !== null) {
            $rejectedKnowledge = $activeLoreIds->diff($knownLoreIds)->count();
            $loreEntryIds = $activeLoreIds->intersect($knownLoreIds)->values();
        }

        $loreChunks = $loreEntryIds->isEmpty()
            ? []
            : LoreChunk::query()
                ->where('chronicle_id', $chronicle->id)
                ->whereIn('lore_entry_id', $loreEntryIds->all())
                ->orderBy('id')
                ->limit(8)
                ->get()
                ->map(fn (LoreChunk $chunk): array => [
                    'id' => (int) $chunk->id,
                    'lore_entry_id' => (int) $chunk->lore_entry_id,
                    'content' => $chunk->content,
                    'visibility' => $chunk->visibility->value,
                ])
                ->all();

        Log::debug('retrieval.world_graphrag', [
            'chronicle_id' => (int) $chronicle->id,
            'character_id' => $asNpc?->id,
            'seed_count' => count($seedEntityIds),
            'visited_entities' => count($bundleEntities),
            'visited_edges' => count($bundleRelations),
            'rejected_knowledge' => $rejectedKnowledge,
            'cte_ms' => $cteMs,
        ]);

        return new WorldGraphBundle(
            (int) $chronicle->id,
            $bundleEntities,
            $bundleRelations,
            $affiliations,
            $relationships,
            $events,
            $loreChunks,
            $seedEntityIds,
        );
    }
}
