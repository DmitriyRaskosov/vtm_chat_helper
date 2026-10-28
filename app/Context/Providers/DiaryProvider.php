<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\DiaryRenderer;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Diary\DiaryRetrievalService;
use App\Models\Message;

class DiaryProvider implements ContextProvider
{
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
        $recentFirstMessageId = $this->resolveRecentFirstMessageId($assembly);

        try {
            $entries = $this->diary->retrieve(
                $character,
                $query,
                DiaryRetrievalService::MAX_ENTRIES,
                $recentFirstMessageId,
                (int) $assembly->scene->id,
            );
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

        $built = DiaryRenderer::buildLines($entries);
        [$content, $truncated] = $this->trimmer->prefix('## Diary', $built['lines'], $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => (int) $character->id,
            'diary_ids' => $built['ids'],
        ], $truncated ? 'lowest_score' : null);
    }

    private function resolveRecentFirstMessageId(ContextAssembly $assembly): ?int
    {
        $id = Message::query()
            ->where('scene_id', $assembly->scene->id)
            ->orderByDesc('id')
            ->limit($assembly->request->historyLimit())
            ->pluck('id')
            ->min();

        return $id === null ? null : (int) $id;
    }
}
