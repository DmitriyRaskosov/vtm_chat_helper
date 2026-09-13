<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Memory\MemoryGraphEdge;
use App\Memory\MemoryGraphNode;
use App\Memory\MemoryGraphRag;

class MemoryGraphProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
        private MemoryGraphRag $graph,
    ) {}

    public function key(): string
    {
        return 'memory_graph';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $character = $assembly->character;
        if ($character === null) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_character']);
        }

        $filters = config('retrieval.memory_graphrag');
        $bundle = $this->graph->expand($character, $assembly->request->prompt);
        $nodes = $bundle->nodes;
        if ($nodes === []) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'seed_ids' => $bundle->seedIds,
                'filters' => $filters,
                'reason' => 'empty',
            ]);
        }

        usort(
            $nodes,
            fn (MemoryGraphNode $left, MemoryGraphNode $right): int => [$right->score, $left->id] <=> [$left->score, $right->id],
        );

        $lines = [];
        $included = [];
        $falseBeliefIds = [];
        foreach ($nodes as $node) {
            $flag = ! empty($node->provenance['is_false_belief']) ? '[false belief] ' : '';
            $type = $node->provenance['node_type'] ?? 'memory';
            $seed = $node->seed ? ', seed' : '';
            $line = "{$flag}{$node->text} ({$type}, depth {$node->depth}{$seed})";
            $trial = [...$lines, $line];
            $candidate = trim("## Personal memory\n".implode("\n", $trial));
            if ($this->estimator->estimate($candidate) > $tokenBudget) {
                break;
            }
            $lines[] = $line;
            $included[] = $node->id;
            if (! empty($node->provenance['is_false_belief'])) {
                $falseBeliefIds[] = $node->id;
            }
        }

        $includedSet = array_flip($included);
        $edgeIds = [];
        foreach ($bundle->edges as $edge) {
            if (! $edge instanceof MemoryGraphEdge) {
                continue;
            }
            if (! isset($includedSet[$edge->sourceNodeId], $includedSet[$edge->targetNodeId])) {
                continue;
            }
            $edgeLine = '#'.$edge->sourceNodeId.' '.$edge->type->value.' #'.$edge->targetNodeId
                .' (w '.number_format($edge->authoredWeight, 2).')';
            $trial = [...$lines, $edgeLine];
            $candidate = trim("## Personal memory\n".implode("\n", $trial));
            if ($this->estimator->estimate($candidate) > $tokenBudget) {
                break;
            }
            $lines[] = $edgeLine;
            $edgeIds[] = $edge->id;
        }

        $truncated = count($included) < count($nodes) || count($edgeIds) < count($bundle->edges);
        [$content] = $this->trimmer->prefix('## Personal memory', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => (int) $character->id,
            'node_ids' => $included,
            'edge_ids' => $edgeIds,
            'seed_ids' => $bundle->seedIds,
            'false_belief_ids' => $falseBeliefIds,
            'filters' => $filters,
        ], $truncated ? 'lowest_score' : null);
    }
}
