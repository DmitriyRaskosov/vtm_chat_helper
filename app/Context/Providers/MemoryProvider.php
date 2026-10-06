<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Memory\MemoryRetrievalService;
use App\Models\Message;
class MemoryProvider implements ContextProvider
{
    private const LIMIT = 6;

    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
        private MemoryRetrievalService $memory,
    ) {}

    public function key(): string
    {
        return 'memory';
    }

    private function buildMemoryQuery(ContextAssembly $assembly): string
    {
        $parts = [];

        $prompt = trim($assembly->request->prompt);

        // Последнее сообщение добавляем ТОЛЬКО если промпт явно ссылается на него
        if ($prompt !== '' && preg_match('/последн/iu', $prompt) === 1) {
            $npcId = $assembly->character?->id;

            $lastMessage = Message::query()
                ->where('scene_id', $assembly->scene->id)
                ->when($npcId !== null, fn ($q) => $q->where(function ($sub) use ($npcId) {
                    $sub->whereNull('author_character_id')
                        ->orWhere('author_character_id', '!=', $npcId);
                }))
                ->orderByDesc('id')
                ->value('body');

            if (is_string($lastMessage) && trim($lastMessage) !== '') {
                $parts[] = trim($lastMessage);
            }
        }

        if ($prompt !== '') {
            $parts[] = $prompt;
        }

        return implode("\n", $parts);
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $character = $assembly->character;
        if ($character === null) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_character']);
        }

        $query = $this->buildMemoryQuery($assembly);
        if (trim($query) === '') {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'empty_query',
            ]);
        }

        try {
            $memories = $this->memory->retrieve($character, $query, self::LIMIT);
        } catch (\Throwable $e) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'retrieval_failed',
                'error' => $e->getMessage(),
            ]);
        }

        if ($memories->isEmpty()) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'no_memories',
            ]);
        }

        $lines = [];
        $ids = [];

        foreach ($memories as $m) {
            $ids[] = (int) $m->id;
            $lines[] = '[memory:'.$m->type.'] '.$m->content;
        }

        [$content, $truncated] = $this->trimmer->prefix('## Memory', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => (int) $character->id,
            'memory_ids' => $ids,
            'query' => mb_substr($query, 0, 200),
        ], $truncated ? 'lowest_score' : null);
    }
}