<?php

namespace App\Retrieval\Hybrid;

use App\Enums\RetrievalCorpus;

final readonly class RetrievalHit
{
    /**
     * @param  array<string, float|int|null>  $scoreComponents
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $provenance
     */
    public function __construct(
        public RetrievalCorpus $corpus,
        public string $sourceType,
        public int $sourceId,
        public string $content,
        public array $scoreComponents,
        public string $reason,
        public array $filters,
        public int $tokenEstimate,
        public array $provenance,
        public ?int $documentId = null,
        public ?int $messageId = null,
    ) {}

    public function score(): float
    {
        $vector = (float) ($this->scoreComponents['vector'] ?? 0);
        $exact = (float) ($this->scoreComponents['exact'] ?? 0);
        $fts = (float) ($this->scoreComponents['fts'] ?? 0);
        $rank = (float) ($this->scoreComponents['rank'] ?? 0);
        $importance = (float) ($this->scoreComponents['importance'] ?? 0);

        return ($exact * 3) + ($vector * 2) + $fts + $rank + ($importance * 0.2);
    }
}
