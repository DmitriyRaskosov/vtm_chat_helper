<?php

namespace App\World;

final readonly class WorldGraphRelation
{
    public function __construct(
        public int $id,
        public string $typeKey,
        public int $sourceEntityId,
        public int $targetEntityId,
        public float $weight,
        public ?string $note,
        public int $depth,
    ) {}
}
