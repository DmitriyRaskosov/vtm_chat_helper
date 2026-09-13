<?php

namespace App\Memory;

use App\Enums\CharacterMemoryEdgeType;
use App\Models\Character;
use App\Models\CharacterMemoryEdge;
use App\Models\CharacterMemoryNode;
use App\Retrieval\CteGuard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MemoryGraphRag
{
    public function __construct(private CharacterMemorySearcher $searcher) {}

    public function expand(Character $character, string $query, int $seedLimit = 5): MemoryGraphBundle
    {
        $seeds = $this->searcher->search($character->id, $query, $seedLimit);

        return $this->expandSeeds($character, $seeds);
    }

    /**
     * @param  Collection<int, CharacterMemoryNode>  $seeds
     */
    public function expandSeeds(Character $character, Collection $seeds): MemoryGraphBundle
    {
        $maxDepth = (int) config('retrieval.memory_graphrag.max_depth', 2);
        $minAbsWeight = (float) config('retrieval.memory_graphrag.min_abs_weight', 0.2);
        $maxNodes = (int) config('retrieval.memory_graphrag.max_nodes', 24);
        $maxEdges = (int) config('retrieval.memory_graphrag.max_edges', 48);
        $depthDecay = (float) config('retrieval.memory_graphrag.depth_decay', 0.6);

        $seeds = $seeds
            ->filter(fn (CharacterMemoryNode $node): bool => (int) $node->character_id === (int) $character->id)
            ->unique('id')
            ->values();

        if ($seeds->isEmpty()) {
            return new MemoryGraphBundle((int) $character->id, [], [], []);
        }

        $seedScores = [];
        foreach ($seeds as $seed) {
            $distance = $seed->neighbor_distance;
            $seedScores[(int) $seed->id] = $distance === null ? 1.0 : max(0.0, 1.0 - (float) $distance);
        }

        $seedIds = $seeds->map(fn (CharacterMemoryNode $node): int => (int) $node->id)->all();
        $seedList = implode(',', $seedIds);
        $walkLimit = max($maxNodes * 6, 48);
        $started = hrtime(true);

        $rows = CteGuard::run(fn () => DB::select(
            "
            WITH RECURSIVE walk AS (
                SELECT
                    n.id AS node_id,
                    0 AS depth,
                    ARRAY[n.id]::bigint[] AS path,
                    NULL::bigint AS via_edge_id,
                    1.0::double precision AS path_strength
                FROM character_memory_nodes n
                WHERE n.character_id = ?
                  AND n.id IN ({$seedList})
                UNION ALL
                SELECT
                    CASE WHEN e.source_node_id = w.node_id THEN e.target_node_id ELSE e.source_node_id END,
                    w.depth + 1,
                    w.path || (CASE WHEN e.source_node_id = w.node_id THEN e.target_node_id ELSE e.source_node_id END),
                    e.id,
                    w.path_strength * ABS(e.authored_weight::double precision)
                FROM walk w
                INNER JOIN character_memory_edges e
                    ON e.character_id = ?
                   AND ABS(e.authored_weight) >= ?
                   AND (
                        e.source_node_id = w.node_id
                        OR (e.bidirectional = true AND e.target_node_id = w.node_id)
                   )
                WHERE w.depth < ?
                  AND NOT (
                    CASE WHEN e.source_node_id = w.node_id THEN e.target_node_id ELSE e.source_node_id END
                  ) = ANY(w.path)
            )
            SELECT node_id, depth, path, via_edge_id, path_strength
            FROM walk
            LIMIT ?
            ",
            [(int) $character->id, (int) $character->id, $minAbsWeight, $maxDepth, $walkLimit],
        ));
        $cteMs = (int) round((hrtime(true) - $started) / 1_000_000);

        $bestByNode = [];
        $edgeIds = [];
        $edgeDepth = [];

        foreach ($rows as $row) {
            $nodeId = (int) $row->node_id;
            $depth = (int) $row->depth;
            $path = $this->pathIds($row->path);
            $seedId = $path[0] ?? $nodeId;
            $seedScore = $seedScores[$seedId] ?? 0.5;
            $pathStrength = (float) $row->path_strength;
            $score = $seedScore * ($depthDecay ** $depth) * $pathStrength;

            if (! isset($bestByNode[$nodeId]) || $score > $bestByNode[$nodeId]['score']) {
                $bestByNode[$nodeId] = [
                    'id' => $nodeId,
                    'depth' => $depth,
                    'score' => $score,
                    'seed' => in_array($nodeId, $seedIds, true),
                    'path' => $path,
                ];
            }

            if ($row->via_edge_id !== null) {
                $edgeId = (int) $row->via_edge_id;
                $edgeIds[$edgeId] = $edgeId;
                $edgeDepth[$edgeId] = min($edgeDepth[$edgeId] ?? $depth, $depth);
            }
        }

        uasort($bestByNode, function (array $left, array $right): int {
            return [$right['score'], $left['id']] <=> [$left['score'], $right['id']];
        });
        $bestByNode = array_slice($bestByNode, 0, $maxNodes, true);
        $keptNodeIds = array_map('intval', array_keys($bestByNode));

        $nodes = CharacterMemoryNode::query()
            ->where('character_id', $character->id)
            ->whereIn('id', $keptNodeIds)
            ->get()
            ->keyBy('id');

        $bundleNodes = [];
        foreach ($bestByNode as $nodeId => $meta) {
            $model = $nodes->get($nodeId);
            if ($model === null) {
                continue;
            }

            $recency = $this->recency($model);
            $score = $meta['score']
                * (((int) $model->importance + 1) / 6)
                * (((int) $model->confidence + 1) / 6)
                * $recency;

            $bundleNodes[] = new MemoryGraphNode(
                (int) $model->id,
                (string) $model->node_text,
                $meta['depth'],
                round($score, 6),
                $meta['seed'],
                [
                    'character_id' => (int) $character->id,
                    'node_type' => $model->node_type->value,
                    'is_false_belief' => $model->is_false_belief,
                    'path' => $meta['path'],
                ],
            );
        }

        $edges = CharacterMemoryEdge::query()
            ->where('character_id', $character->id)
            ->whereIn('id', array_values($edgeIds))
            ->whereIn('source_node_id', $keptNodeIds)
            ->whereIn('target_node_id', $keptNodeIds)
            ->orderBy('id')
            ->get()
            ->take($maxEdges);

        $bundleEdges = $edges->map(function (CharacterMemoryEdge $edge) use ($edgeDepth): MemoryGraphEdge {
            return new MemoryGraphEdge(
                (int) $edge->id,
                (int) $edge->source_node_id,
                (int) $edge->target_node_id,
                $edge->relation_type instanceof CharacterMemoryEdgeType
                    ? $edge->relation_type
                    : CharacterMemoryEdgeType::from((string) $edge->relation_type),
                (float) $edge->authored_weight,
                (bool) $edge->bidirectional,
                $edgeDepth[(int) $edge->id] ?? 1,
            );
        })->all();

        Log::debug('retrieval.memory_graphrag', [
            'character_id' => (int) $character->id,
            'seed_count' => count($seedIds),
            'visited_nodes' => count($bundleNodes),
            'visited_edges' => count($bundleEdges),
            'cte_ms' => $cteMs,
        ]);

        return new MemoryGraphBundle((int) $character->id, $bundleNodes, $bundleEdges, $seedIds);
    }

    /**
     * @return list<int>
     */
    private function pathIds(mixed $path): array
    {
        if (is_array($path)) {
            return array_map('intval', array_values($path));
        }

        $path = trim((string) $path, '{}');
        if ($path === '') {
            return [];
        }

        return array_map('intval', explode(',', $path));
    }

    private function recency(CharacterMemoryNode $node): float
    {
        $timestamp = $node->updated_at?->getTimestamp() ?? time();
        $days = max(0, (time() - $timestamp) / 86400);

        return 1 / (1 + ($days / 30));
    }
}
