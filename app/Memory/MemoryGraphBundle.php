<?php

namespace App\Memory;

final readonly class MemoryGraphBundle
{
    /**
     * @param  list<MemoryGraphNode>  $nodes
     * @param  list<MemoryGraphEdge>  $edges
     * @param  list<int>  $seedIds
     */
    public function __construct(
        public int $characterId,
        public array $nodes,
        public array $edges,
        public array $seedIds,
    ) {}
}
