<?php

namespace App\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaChatProvider implements ChatProvider
{
    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, int|float|string|bool>  $options
     */
    public function chat(array $messages, array $options = []): string
    {
        $turn = $this->chatTurn($messages, $options);
        if ($turn->content === '') {
            throw new RuntimeException('Ollama returned an empty chat response.');
        }

        return $turn->content;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, int|float|string|bool>  $options
     * @param  list<array<string, mixed>>  $tools
     */
    public function chatTurn(array $messages, array $options = [], array $tools = []): ChatTurn
    {
        $options = array_replace([
            'num_ctx' => (int) config('ollama.context_length'),
            'num_predict' => (int) config('ollama.max_output_tokens'),
        ], $options);

        $payload = [
            'model' => config('ollama.chat_model'),
            'stream' => false,
            'messages' => $messages,
            'options' => $options,
        ];

        if ($tools !== []) {
            $payload['tools'] = $tools;
        }

        $response = Http::timeout(180)
            ->baseUrl((string) config('ollama.url'))
            ->post('/api/chat', $payload);

        $response->throw();

        $message = $response->json('message');
        if (! is_array($message)) {
            throw new RuntimeException('Ollama returned an empty chat response.');
        }

        $content = $message['content'] ?? '';
        $content = is_string($content) ? $content : '';
        $rawToolCalls = $message['tool_calls'] ?? [];
        $rawToolCalls = is_array($rawToolCalls) ? array_values($rawToolCalls) : [];

        $toolCalls = [];
        foreach ($rawToolCalls as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $function = $raw['function'] ?? [];
            $name = is_array($function) ? ($function['name'] ?? '') : '';
            if (! is_string($name) || $name === '') {
                continue;
            }

            $arguments = is_array($function) ? ($function['arguments'] ?? []) : [];
            if (is_string($arguments)) {
                $decoded = json_decode($arguments, true);
                $arguments = is_array($decoded) ? $decoded : [];
            }
            if (! is_array($arguments)) {
                $arguments = [];
            }

            $toolCalls[] = new ToolCall($name, $arguments);
        }

        if ($content === '' && $toolCalls === []) {
            throw new RuntimeException('Ollama returned an empty chat response.');
        }

        return new ChatTurn($content, $toolCalls, $rawToolCalls);
    }

    public function complete(string $prompt): string
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ]);
    }
}
