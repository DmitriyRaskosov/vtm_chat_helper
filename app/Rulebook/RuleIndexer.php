<?php

namespace App\Rulebook;

use App\Context\TokenEstimator;
use App\Enums\RuleDocumentStatus;
use App\Models\ChronicleRuleOverride;
use App\Models\GameRuleChunk;
use App\Models\RuleDocument;
use App\Models\RuleDocumentVersion;
use App\Rag\EmbeddingProvider;
use App\Rag\TextChunker;
use Illuminate\Support\Facades\DB;

class RuleIndexer
{
    public function __construct(
        private EmbeddingProvider $embeddings,
        private TokenEstimator $tokens,
        private TextChunker $chunker,
    ) {}

    public function indexVersion(RuleDocumentVersion $version): int
    {
        $version->loadMissing('document.ruleset');

        if ($version->status !== RuleDocumentStatus::Approved) {
            return DB::transaction(function () use ($version): int {
                GameRuleChunk::query()
                    ->where('rule_document_id', $version->rule_document_id)
                    ->whereNull('chronicle_rule_override_id')
                    ->delete();

                return 0;
            });
        }

        $ruleset = $version->document->ruleset;
        $rows = $this->rowsFromText(
            $version->canonical_text,
            [
                'ruleset_id' => $ruleset->id,
                'edition' => $ruleset->edition,
                'language' => $ruleset->language,
                'rule_document_id' => $version->rule_document_id,
                'rule_document_version_id' => $version->id,
                'chronicle_rule_override_id' => null,
                'chronicle_id' => null,
                'section_path' => $version->section,
                'source_reference' => $version->source_reference,
                'metadata' => [
                    'estimator' => $this->tokens->version(),
                    'rule_version' => $version->version,
                ],
            ],
        );

        return DB::transaction(function () use ($version, $rows): int {
            GameRuleChunk::query()
                ->where('rule_document_id', $version->rule_document_id)
                ->whereNull('chronicle_rule_override_id')
                ->delete();

            foreach ($rows as $row) {
                GameRuleChunk::query()->create($row);
            }

            return count($rows);
        });
    }

    public function indexOverride(ChronicleRuleOverride $override): int
    {
        $override->loadMissing('document.ruleset');

        if ($override->status !== RuleDocumentStatus::Approved) {
            return DB::transaction(function () use ($override): int {
                GameRuleChunk::query()
                    ->where('chronicle_rule_override_id', $override->id)
                    ->delete();

                return 0;
            });
        }

        $ruleset = $override->document->ruleset;
        $rows = $this->rowsFromText(
            $override->override_text,
            [
                'ruleset_id' => $ruleset->id,
                'edition' => $ruleset->edition,
                'language' => $ruleset->language,
                'rule_document_id' => $override->rule_document_id,
                'rule_document_version_id' => null,
                'chronicle_rule_override_id' => $override->id,
                'chronicle_id' => $override->chronicle_id,
                'section_path' => $override->document->section,
                'source_reference' => $override->document->source_reference,
                'metadata' => [
                    'estimator' => $this->tokens->version(),
                    'override_version' => $override->current_version,
                ],
            ],
        );

        return DB::transaction(function () use ($override, $rows): int {
            GameRuleChunk::query()
                ->where('chronicle_rule_override_id', $override->id)
                ->delete();

            foreach ($rows as $row) {
                GameRuleChunk::query()->create($row);
            }

            return count($rows);
        });
    }

    public function rebuildDocument(RuleDocument $document): int
    {
        $document->refresh();

        if ($document->status !== RuleDocumentStatus::Approved) {
            GameRuleChunk::query()
                ->where('rule_document_id', $document->id)
                ->whereNull('chronicle_rule_override_id')
                ->delete();

            return 0;
        }

        $version = RuleDocumentVersion::query()
            ->where('rule_document_id', $document->id)
            ->where('version', $document->current_version)
            ->firstOrFail();

        return $this->indexVersion($version);
    }

    /**
     * @param  array<string, mixed>  $base
     * @return list<array<string, mixed>>
     */
    private function rowsFromText(string $text, array $base): array
    {
        $rows = [];
        $chunkIndex = 0;

        foreach ($this->chunker->split($text) as $part) {
            $rows[] = [
                ...$base,
                'chunk_index' => $chunkIndex++,
                'content' => $part,
                'token_estimate' => $this->tokens->estimate($part),
                'embedding' => $this->embeddings->embed($part),
            ];
        }

        return $rows;
    }
}
