<?php

namespace App\World;

final readonly class WorldGraphEntity
{
    public function __construct(
        public int $id,
        public string $canonicalName,
        public string $entityType,
        public ?string $shortDescription,
        public int $depth,
        public bool $seed,
    ) {}
}
