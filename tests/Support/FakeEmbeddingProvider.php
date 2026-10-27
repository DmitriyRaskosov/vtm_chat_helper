<?php

namespace Tests\Support;

use App\Rag\EmbeddingProvider;

final class FakeEmbeddingProvider implements EmbeddingProvider
{
    /** @var list<array{type: string, text?: string, texts?: list<string>}> */
    private array $calls = [];

    public function embed(string $text): array
    {
        $this->calls[] = ['type' => 'embed', 'text' => $text];

        return $this->vectorFor($text);
    }

    public function embedBatch(array $texts): array
    {
        $this->calls[] = ['type' => 'embedBatch', 'texts' => array_values($texts)];

        return array_map(fn (string $text): array => $this->vectorFor($text), array_values($texts));
    }

    public function dimensions(): int
    {
        return 1024;
    }

    public function model(): string
    {
        return 'fake-embedding';
    }

    /**
     * @return list<array{type: string, text?: string, texts?: list<string>}>
     */
    public function calls(): array
    {
        return $this->calls;
    }

    /**
     * @return list<float>
     */
    private function vectorFor(string $text): array
    {
        $hash = hash('sha256', $text);
        $vector = [];

        for ($i = 0; $i < 1024; $i++) {
            $byte = hexdec(substr($hash, ($i * 2) % 64, 2));
            $vector[] = ($byte / 255.0) * 2 - 1;
        }

        return $vector;
    }
}
