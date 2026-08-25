<?php

namespace App\Rag;

interface EmbeddingProvider
{
    /**
     * @return list<float>
     */
    public function embed(string $text): array;
}
