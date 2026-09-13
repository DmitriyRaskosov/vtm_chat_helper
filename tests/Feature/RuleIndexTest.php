<?php

namespace Tests\Feature;

use App\Enums\LoreEntryStatus;
use App\Enums\RuleDocumentStatus;
use App\Lore\LoreEntryService;
use App\Lore\LoreIndexer;
use App\Models\Chronicle;
use App\Models\GameRuleChunk;
use App\Models\LoreChunk;
use App\Models\RuleDocument;
use App\Models\Ruleset;
use App\Rulebook\RuleDocumentService;
use App\Rulebook\RuleIndexer;
use App\Rulebook\RuleSearcher;
use App\Rulebook\RulesetService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class RuleIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_chunks_live_in_the_dedicated_rules_corpus(): void
    {
        [$document] = $this->approvedDominate();
        $count = $this->app->make(RuleIndexer::class)->rebuildDocument($document);

        $this->assertSame(1, $count);
        $this->assertDatabaseCount('game_rule_chunks', 1);
        $this->assertDatabaseCount('lore_chunks', 0);
        $this->assertDatabaseCount('character_bio_chunks', 0);
        $this->assertDatabaseHas('game_rule_chunks', [
            'rule_document_id' => $document->id,
            'content' => 'Доминирование подчиняет волю жертвы.',
            'source_reference' => 'V20 p.152',
        ]);
    }

    public function test_search_requires_ruleset_or_edition_then_applies_override(): void
    {
        [$document, $ruleset] = $this->approvedDominate();
        $indexer = $this->app->make(RuleIndexer::class);
        $indexer->rebuildDocument($document);

        $prague = Chronicle::factory()->create();
        $berlin = Chronicle::factory()->create();
        $override = $this->app->make(RuleDocumentService::class)->override(
            $prague,
            $document,
            'В Праге Dominate не действует на гулей князя.',
            'House rule.',
        );
        $indexer->indexOverride($override);

        $searcher = $this->app->make(RuleSearcher::class);

        try {
            $searcher->search('Доминирование подчиняет волю жертвы.');
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('Rule search requires a ruleset or edition.', $e->getMessage());
        }

        $base = $searcher->search('Доминирование подчиняет волю жертвы.', rulesetId: $ruleset->id, edition: 'v20');
        $this->assertNotEmpty($base);
        $this->assertTrue($base->every(fn (GameRuleChunk $chunk): bool => $chunk->chronicle_id === null));

        $pragueHits = $searcher->search(
            'В Праге Dominate не действует на гулей князя.',
            rulesetId: $ruleset->id,
            edition: 'v20',
            chronicleId: $prague->id,
        );
        $this->assertNotEmpty($pragueHits);
        $this->assertTrue($pragueHits->every(fn (GameRuleChunk $chunk): bool => (int) $chunk->chronicle_id === (int) $prague->id));
        $this->assertFalse($pragueHits->contains(fn (GameRuleChunk $chunk): bool => $chunk->content === 'Доминирование подчиняет волю жертвы.'));

        $berlinHits = $searcher->search(
            'Доминирование подчиняет волю жертвы.',
            rulesetId: $ruleset->id,
            edition: 'v20',
            chronicleId: $berlin->id,
        );
        $this->assertNotEmpty($berlinHits);
        $this->assertTrue($berlinHits->every(fn (GameRuleChunk $chunk): bool => $chunk->chronicle_id === null));
    }

    public function test_rule_search_does_not_return_lore_chunks(): void
    {
        [$document, $ruleset] = $this->approvedDominate();
        $this->app->make(RuleIndexer::class)->rebuildDocument($document);

        $chronicle = Chronicle::factory()->create();
        $lore = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Dominate',
            'canonical_text' => 'Доминирование подчиняет волю жертвы.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($lore);

        $hits = $this->app->make(RuleSearcher::class)->search(
            'Доминирование подчиняет волю жертвы.',
            rulesetId: $ruleset->id,
        );

        $this->assertNotEmpty($hits);
        $this->assertTrue($hits->every(fn ($chunk): bool => $chunk instanceof GameRuleChunk));
        $this->assertGreaterThan(0, LoreChunk::query()->count());
        $this->assertSame(0, LoreChunk::query()->whereIn('id', $hits->pluck('id'))->count());
    }

    public function test_archived_rule_documents_are_excluded_from_search(): void
    {
        [$document, $ruleset] = $this->approvedDominate();
        $this->app->make(RuleIndexer::class)->rebuildDocument($document);
        $this->app->make(RuleDocumentService::class)->archive($document);

        $hits = $this->app->make(RuleSearcher::class)->search(
            'Доминирование подчиняет волю жертвы.',
            rulesetId: $ruleset->id,
        );

        $this->assertTrue($hits->isEmpty());
        $this->assertDatabaseCount('game_rule_chunks', 1);
    }

    /**
     * @return array{0: RuleDocument, 1: Ruleset}
     */
    private function approvedDominate(): array
    {
        $ruleset = $this->app->make(RulesetService::class)->create('VtM', 'v20', 'ru');
        $document = $this->app->make(RuleDocumentService::class)->publish($ruleset, [
            'title' => 'Dominate',
            'section' => 'disciplines.dominate',
            'canonical_text' => 'Доминирование подчиняет волю жертвы.',
            'source_reference' => 'V20 p.152',
            'status' => RuleDocumentStatus::Approved,
        ], 'v1');

        return [$document, $ruleset];
    }
}
