<?php

namespace App\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaChatProvider implements ChatProvider
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function chat(array $messages): string
    {
        $response = Http::timeout(180)
            ->baseUrl((string) config('ollama.url'))
            ->post('/api/chat', [
                'model' => config('ollama.chat_model'),
                'stream' => false,
                'messages' => $messages,
            ]);

        $response->throw();

        $text = $response->json('message.content');

        if (! is_string($text) || $text === '') {
            throw new RuntimeException('Ollama returned an empty chat response.');
        }

        return $text;
    }

    public function complete(string $prompt): string
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ]);
    }
}
