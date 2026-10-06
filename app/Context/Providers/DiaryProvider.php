<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Diary\DiaryRetrievalService;

class DiaryProvider implements ContextProvider
{
    private const LIMIT = 3;

    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
        private DiaryRetrievalService $diary,
    ) {}

    public function key(): string
    {
        return 'diary';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $character = $assembly->character;
        if ($character === null) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_character']);
        }

        $query = trim($assembly->request->prompt);

        try {
            $entries = $this->diary->retrieve($character, $query, self::LIMIT);
        } catch (\Throwable $e) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'retrieval_failed',
                'error' => $e->getMessage(),
            ]);
        }

        if ($entries->isEmpty()) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'no_entries',
            ]);
        }

        $lines = [];
        $ids = [];
        $isFirst = true;

        foreach ($entries as $entry) {
            $ids[] = (int) $entry->id;
            $label = $isFirst ? '[diary — most recent]' : '[diary — earlier]';
            $paragraphs = preg_split('/\n\s*\n/u', trim($entry->entry)) ?: [];
            $first = true;

            foreach ($paragraphs as $p) {
                $p = trim((string) $p);
                if ($p === '') {
                    continue;
                }
                $lines[] = $first ? $label.' '.$p : $p;
                $first = false;
            }

            $isFirst = false;
        }

        [$content, $truncated] = $this->trimmer->prefix('## Diary', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => (int) $character->id,
            'diary_ids' => $ids,
        ], $truncated ? 'lowest_score' : null);
    }
}