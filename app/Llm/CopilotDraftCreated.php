<?php

namespace App\Llm;

final readonly class CopilotDraftCreated
{
    /**
     * @param  list<string>  $drafts
     */
    public function __construct(
        public int $copilotRequestId,
        public array $drafts,
    ) {}
}
