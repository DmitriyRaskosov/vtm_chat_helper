<?php

namespace App\Llm;

final readonly class ChatTurn
{
    public function __construct(
        public string $content,
        public ?string $finishReason = null,
    ) {}
}
