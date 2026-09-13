<?php

namespace App\Memory;

final readonly class MemoryGraphNode
{
    /**
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public int $id,
        public string $text,
        public int $depth,
        public float $score,
        public bool $seed,
        public array $provenance,
    ) {}
}
