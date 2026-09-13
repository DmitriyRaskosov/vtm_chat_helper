<?php

namespace App\Memory;

use App\Enums\CharacterMemoryEdgeType;

final readonly class MemoryGraphEdge
{
    public function __construct(
        public int $id,
        public int $sourceNodeId,
        public int $targetNodeId,
        public CharacterMemoryEdgeType $type,
        public float $authoredWeight,
        public bool $bidirectional,
        public int $depth,
    ) {}
}
