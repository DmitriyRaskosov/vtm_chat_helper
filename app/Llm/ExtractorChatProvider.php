<?php

namespace App\Llm;

use App\Extractor\ExtractionTokenLimitException;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class ExtractorChatProvider extends OllamaChatProvider
{
    public function __construct()
    {
        parent::__construct((string) config('extractor.ollama_model'));
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, int|float|string|bool>  $options
     */
    public function chat(array $messages, array $options = []): string
    {
        $profile = (string) ($options['profile'] ?? 'lore');
        unset($options['profile']);

        $defaultOutput = (int) config("extractor.profiles.{$profile}.output_tokens", 7128);

        $options = array_replace([
            'num_ctx' => (int) config('ollama.context_length'),
            'num_predict' => $defaultOutput,
            'temperature' => (float) config('extractor.temperature', 0.1),
        ], $options);

        $payload = [
            'model' => (string) config('extractor.ollama_model'),
            'stream' => false,
            'messages' => $messages,
            'options' => $options,
        ];

        $format = config('ollama.chat_format');
        if (is_string($format) && $format !== '') {
            $payload['format'] = $format;
        }

        if (config('extractor.think') === false) {
            $payload['think'] = false;
        }

        $response = Http::timeout($this->httpTimeoutSeconds())
            ->baseUrl((string) config('ollama.url'))
            ->post('/api/chat', $payload);

        $response->throw();

        $doneReason = $response->json('done_reason');
        if ($doneReason === 'length') {
            throw new ExtractionTokenLimitException($profile);
        }

        $message = $response->json('message');
        if (! is_array($message)) {
            throw new RuntimeException('Ollama returned an empty chat response.');
        }

        $content = $message['content'] ?? '';
        $content = is_string($content) ? $content : '';

        if ($content === '') {
            $thinking = $message['thinking'] ?? '';
            $content = is_string($thinking) ? $thinking : '';
        }

        if ($content === '') {
            throw new RuntimeException('Ollama returned an empty chat response.');
        }

        return $content;
    }

    protected function httpTimeoutSeconds(): int
    {
        return (int) config('extractor.http_timeout_seconds');
    }
}
