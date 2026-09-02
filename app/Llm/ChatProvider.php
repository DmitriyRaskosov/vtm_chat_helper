<?php

namespace App\Llm;

interface ChatProvider
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, int|float|string|bool>  $options
     */
    public function chat(array $messages, array $options = []): string;

    public function complete(string $prompt): string;
}
