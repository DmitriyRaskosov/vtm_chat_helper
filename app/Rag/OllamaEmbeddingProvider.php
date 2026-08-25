<?php

namespace App\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaEmbeddingProvider implements EmbeddingProvider
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $response = Http::timeout(60)
            ->baseUrl((string) config('ollama.url'))
            ->post('/api/embed', [
                'model' => config('ollama.embed_model'),
                'input' => $text,
            ]);

        $response->throw();

        $vector = $response->json('embeddings.0');

        if (! is_array($vector) || $vector === []) {
            throw new RuntimeException('Ollama returned an empty embedding.');
        }

        $expected = (int) config('rag.dimensions');

        if (count($vector) !== $expected) {
            throw new RuntimeException("Ollama embedding length is ".count($vector).", expected {$expected}.");
        }

        return array_map(floatval(...), $vector);
    }
}
