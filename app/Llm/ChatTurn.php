<?php

namespace App\Llm;

final readonly class ChatTurn
{
    /**
     * @param  list<ToolCall>  $toolCalls
     * @param  list<array<string, mixed>>  $rawToolCalls
     */
    public function __construct(
        public string $content,
        public array $toolCalls = [],
        public array $rawToolCalls = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toAssistantMessage(): array
    {
        $message = [
            'role' => 'assistant',
            'content' => $this->content,
        ];

        if ($this->rawToolCalls !== []) {
            $message['tool_calls'] = $this->rawToolCalls;
        }

        return $message;
    }
}
