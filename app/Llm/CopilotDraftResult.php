<?php

namespace App\Llm;

final readonly class CopilotDraftResult
{
    /**
     * @param  list<string>  $drafts
     * @param  array<string, mixed>  $contextMetadata
     */
    public function __construct(
        public array $drafts,
        public array $contextMetadata,
        public string $model,
        public string $builderVersion,
        public string $promptVersion,
    ) {}
}
