<?php

namespace Database\Factories;

use App\Models\GameRuleChunk;
use App\Models\RuleDocumentVersion;
use App\Rag\StubEmbeddingProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameRuleChunk>
 */
class GameRuleChunkFactory extends Factory
{
    public function definition(): array
    {
        $content = 'Доминирование подчиняет волю жертвы.';

        return [
            'rule_document_version_id' => RuleDocumentVersion::factory()->state(['status' => 'approved']),
            'ruleset_id' => fn (array $attributes) => RuleDocumentVersion::query()
                ->with('document')
                ->findOrFail($attributes['rule_document_version_id'])
                ->document
                ->ruleset_id,
            'edition' => 'v20',
            'language' => 'ru',
            'rule_document_id' => fn (array $attributes) => RuleDocumentVersion::query()
                ->findOrFail($attributes['rule_document_version_id'])
                ->rule_document_id,
            'chronicle_rule_override_id' => null,
            'chronicle_id' => null,
            'chunk_index' => 0,
            'section_path' => 'disciplines.dominate',
            'content' => $content,
            'source_reference' => 'V20 p.152',
            'token_estimate' => 10,
            'metadata' => null,
            'embedding' => (new StubEmbeddingProvider)->embed($content),
        ];
    }
}
