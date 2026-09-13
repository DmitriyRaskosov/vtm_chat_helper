<?php

namespace App\World;

final readonly class WorldGraphBundle
{
    /**
     * @param  list<WorldGraphEntity>  $entities
     * @param  list<WorldGraphRelation>  $relations
     * @param  list<array<string, mixed>>  $affiliations
     * @param  list<array<string, mixed>>  $relationships
     * @param  list<array<string, mixed>>  $events
     * @param  list<array<string, mixed>>  $loreChunks
     * @param  list<int>  $seedIds
     */
    public function __construct(
        public int $chronicleId,
        public array $entities,
        public array $relations,
        public array $affiliations,
        public array $relationships,
        public array $events,
        public array $loreChunks,
        public array $seedIds,
    ) {}
}
