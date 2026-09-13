<?php

namespace App\Retrieval\Hybrid;

final readonly class RetrievalRequest
{
    public function __construct(
        public int $chronicleId,
        public string $query,
        public ?int $characterId = null,
        public ?int $gameSessionId = null,
        public ?int $sceneId = null,
        public ?int $rulesetId = null,
        public ?string $edition = null,
        public bool $asNpc = false,
        public bool $includeMessages = true,
        public bool $includeBio = true,
        public bool $includeMemory = true,
        public bool $includeLore = true,
        public bool $includeRules = true,
        public bool $includeRelations = true,
        public ?int $perCorpusLimit = null,
    ) {}
}
