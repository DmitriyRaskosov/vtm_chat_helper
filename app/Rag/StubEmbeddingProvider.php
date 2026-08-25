<?php

namespace App\Rag;

class StubEmbeddingProvider implements EmbeddingProvider
{
    /**
     * Deterministic vector from text (not a real model). Replace with Ollama later.
     *
     * @return list<float>
     */
    public function embed(string $text): array
    {
        $dim = (int) config('rag.dimensions');
        $raw = hash('sha256', $text, true);
        $values = [];

        while (count($values) < $dim) {
            foreach (str_split($raw) as $byte) {
                $values[] = (ord($byte) / 127.5) - 1.0;
                if (count($values) >= $dim) {
                    break;
                }
            }
            $raw = hash('sha256', $raw, true);
        }

        $norm = sqrt(array_sum(array_map(fn (float $v): float => $v * $v, $values))) ?: 1.0;

        return array_map(fn (float $v): float => $v / $norm, $values);
    }
}
