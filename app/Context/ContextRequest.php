<?php

namespace App\Context;

final readonly class ContextRequest
{
    public function __construct(
        public string $npcName,
        public string $prompt,
        public int $sceneId,
        public int $draftCount,
        public ?int $storytellerId = null,
        public ?int $gameSessionId = null,
        public ?int $characterId = null,
    ) {}
}
