<?php

namespace App\Context;

final readonly class ContextBuild
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public array $messages,
        public array $metadata,
    ) {}
}
