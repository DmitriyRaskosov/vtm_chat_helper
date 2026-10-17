<?php

namespace App\Llm;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DeepseekChatProvider implements ChatProvider
{
    public function __construct(
        private ?string $chatModel = null,
        private ?string $apiKey = null,
        private ?string $baseUrl = null,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, int|float|string|bool>  $options
     */
    public function chat(array $messages, array $options = []): string
    {
        $turn = $this->chatTurn($messages, $options);
        if ($turn->content === '') {
            throw new RuntimeException('DeepSeek returned an empty chat response.');
        }

        return $turn->content;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, int|float|string|bool>  $options
     */
    public function chatTurn(array $messages, array $options = []): ChatTurn
    {
        $maxTokens = (int) ($options['max_tokens']
            ?? $options['num_predict']
            ?? config('llm.max_output_tokens'));

        $payload = [
            'model' => $this->chatModel ?? config('llm.deepseek.chat_model'),
            'messages' => $messages,
            'stream' => false,
            'max_tokens' => $maxTokens,
            'temperature' => (float) ($options['temperature'] ?? config('llm.temperature', 0.7)),
        ];

        if (config('llm.json_mode')) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $response = Http::timeout($this->httpTimeoutSeconds())
            ->withToken($this->apiKey ?? (string) config('llm.deepseek.api_key'))
            ->baseUrl((string) ($this->baseUrl ?? config('llm.deepseek.base_url')))
            ->acceptJson()
            ->post('/chat/completions', $payload);

        $response->throw();

        $message = $response->json('choices.0.message');
        if (! is_array($message)) {
            throw new RuntimeException('DeepSeek returned an empty chat response.');
        }

        $content = $message['content'] ?? '';
        $content = is_string($content) ? $content : '';

        if ($content === '') {
            throw new RuntimeException('DeepSeek returned an empty chat response.');
        }

        return new ChatTurn($content);
    }

    public function complete(string $prompt): string
    {
        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ]);
    }

    protected function httpTimeoutSeconds(): int
    {
        return (int) config('llm.deepseek.timeout', 120);
    }
}
