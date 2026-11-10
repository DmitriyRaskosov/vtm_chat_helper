<?php

namespace App\Messages;

final readonly class PostMessageData
{
    public function __construct(
        public string $body,
        public bool $isOoc,
        public bool $isNarration,
        public ?int $characterId,
        public ?int $copilotRequestId,
        public ?int $copilotDraftIndex,
    ) {}
}
