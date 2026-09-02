<?php

namespace App\Context;

final readonly class GeneratedSummary
{
    /**
     * @param  array<string, list<string>>  $metadata
     */
    public function __construct(
        public string $content,
        public array $metadata,
    ) {}
}
