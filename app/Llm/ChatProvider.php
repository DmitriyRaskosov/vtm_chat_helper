<?php

namespace App\Llm;

interface ChatProvider
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages): string;

    public function complete(string $prompt): string;
}
