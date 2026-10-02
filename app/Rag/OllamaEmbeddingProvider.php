<?php

namespace App\Rag;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OllamaEmbeddingProvider implements EmbeddingProvider
{
    public function __construct(
        private ?string $url = null,
        private ?string $model = null,
        private ?int $dimensions = null,
    ) {}

    public function embed(string $text): array
    {
        $result = $this->embedBatch([$text]);

        return $result[0];
    }

    public function embedBatch(array $texts): array
    {
        if ($texts === []) {
            return [];
        }

        $response = Http::timeout($this->timeout())
            ->baseUrl($this->baseUrl())
            ->acceptJson()
            ->post('/api/embed', [
                'model' => $this->modelName(),
                'input' => array_values($texts),
            ]);

        $response->throw();

        $embeddings = $response->json('embeddings');
        if (! is_array($embeddings) || count($embeddings) !== count($texts)) {
            throw new RuntimeException('Ollama embedding response is malformed.');
        }

        $expected = $this->dimensions();
        $result = [];

        foreach ($embeddings as $index => $embedding) {
            if (! is_array($embedding)) {
                throw new RuntimeException("Embedding at index {$index} is not an array.");
            }
            if (count($embedding) !== $expected) {
                throw new RuntimeException(
                    "Embedding at index {$index} has ".count($embedding)." dimensions, expected {$expected}.",
                );
            }
            $result[] = array_map('floatval', $embedding);
        }

        return $result;
    }

    public function dimensions(): int
    {
        return $this->dimensions ?? (int) config('embedding.ollama.dimensions');
    }

    public function model(): string
    {
        return $this->modelName();
    }

    private function baseUrl(): string
    {
        return $this->url ?? (string) config('embedding.ollama.url');
    }

    private function modelName(): string
    {
        return $this->model ?? (string) config('embedding.ollama.model');
    }

    private function timeout(): int
    {
        return (int) config('embedding.ollama.timeout', 60);
    }
}