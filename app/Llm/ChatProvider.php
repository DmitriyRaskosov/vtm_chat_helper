<?php

namespace App\Llm;

interface ChatProvider
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, int|float|string|bool>  $options
     */
    public function chat(array $messages, array $options = []): string;

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, int|float|string|bool>  $options
     * @param  list<array<string, mixed>>  $tools
     */
    public function chatTurn(array $messages, array $options = [], array $tools = []): ChatTurn;

    public function complete(string $prompt): string;
}
