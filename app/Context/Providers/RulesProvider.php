<?php

namespace App\Context\Providers;

use App\Character\CharacterRuleKnowledgeService;
use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\GameRuleChunk;
use App\Models\RuleDocument;
use App\Rulebook\RuleSearcher;

class RulesProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
        private RuleSearcher $searcher,
        private CharacterRuleKnowledgeService $knowledge,
    ) {}

    public function key(): string
    {
        return 'rules';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $character = $assembly->character;
        if ($character === null) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_character']);
        }

        $knownIds = $this->knowledge->knownRuleDocumentIds($character);
        if ($knownIds === []) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'no_grants',
            ]);
        }

        $rulesetIds = RuleDocument::query()
            ->whereIn('id', $knownIds)
            ->pluck('ruleset_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();

        $chunks = collect();
        foreach ($rulesetIds as $rulesetId) {
            $chunks = $chunks->concat(
                $this->searcher->searchForCharacter(
                    $character,
                    $assembly->request->prompt,
                    $rulesetId,
                ),
            );
        }
        $chunks = $chunks->unique('id')->values();

        if ($chunks->isEmpty()) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'rule_document_ids' => $knownIds,
                'ruleset_ids' => $rulesetIds,
                'reason' => 'empty',
            ]);
        }

        $lines = [];
        $chunkIds = [];
        $documentIds = [];
        foreach ($chunks as $chunk) {
            if (! $chunk instanceof GameRuleChunk) {
                continue;
            }
            $chunkIds[] = (int) $chunk->id;
            $documentIds[] = (int) $chunk->rule_document_id;
            $reference = is_string($chunk->source_reference) && $chunk->source_reference !== ''
                ? ' ('.$chunk->source_reference.')'
                : '';
            $lines[] = $chunk->content.$reference;
        }

        [$content, $truncated] = $this->trimmer->prefix('## Rules', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => (int) $character->id,
            'chunk_ids' => $chunkIds,
            'rule_document_ids' => array_values(array_unique($documentIds)),
            'ruleset_ids' => $rulesetIds,
            'filters' => [
                'known_rule_document_ids' => $knownIds,
            ],
        ], $truncated ? 'lowest_score' : null);
    }
}
