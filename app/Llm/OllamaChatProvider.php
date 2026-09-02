<?php

namespace App\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaChatProvider implements ChatProvider
{
    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  array<string, int|float|string|bool>  $options
     */
    public function chat(array $messages, array $options = []): string
    {
        $options = array_replace([
            'num_ctx' => (int) config('ollama.context_length'),
            'num_predict' => (int) config('ollama.max_output_tokens'),
        ], $options);

        $response = Http::timeout(180)
            ->baseUrl((string) config('ollama.url'))
            ->post('/api/chat', [
                'model' => config('ollama.chat_model'),
                'stream' => false,
                'messages' => $messages,
                'options' => $options,
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
