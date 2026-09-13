<?php

namespace Tests\Feature;

use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Lore\LoreEntryService;
use App\Lore\LoreIndexer;
use App\Lore\LoreSearcher;
use App\Models\Chronicle;
use App\Models\LoreChunk;
use App\Models\LoreEntry;
use App\Models\LoreEntryVersion;
use App\Rag\EmbeddingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class LoreIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_does_not_index_until_an_approved_version_is_indexed(): void
    {
        $chronicle = Chronicle::factory()->create();
        $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');

        $this->assertDatabaseCount('lore_chunks', 0);
        $this->assertFalse(Schema::hasTable('rag_chunks'));
    }

    public function test_indexer_writes_chunks_outside_rag_chunks(): void
    {
        $entry = $this->publishApproved('Маскарад', 'Каиниты не раскрывают свою природу смертным.');
        $version = LoreEntryVersion::query()->firstOrFail();
        $count = $this->app->make(LoreIndexer::class)->indexVersion($version);

        $this->assertSame(2, $count);
        $this->assertDatabaseCount('lore_chunks', 2);
        $this->assertFalse(Schema::hasTable('rag_chunks'));
        $this->assertDatabaseHas('lore_chunks', [
            'lore_entry_id' => $entry->id,
            'chronicle_id' => $entry->chronicle_id,
            'content' => 'Каиниты не раскрывают свою природу смертным.',
        ]);
    }

    public function test_draft_version_is_not_kept_in_the_index(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(LoreEntryService::class);
        $entry = $service->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);

        $service->publish($chronicle, [
            'canonical_text' => 'Черновик правки.',
            'status' => LoreEntryStatus::Draft,
        ], 'v2', $entry);

        $this->assertSame(0, $this->app->make(LoreIndexer::class)->rebuildApproved($entry->fresh()));
        $this->assertDatabaseCount('lore_chunks', 0);
    }

    public function test_index_can_be_dropped_and_rebuilt_from_approved_version(): void
    {
        $entry = $this->publishApproved('Маскарад', 'Каиниты не раскрывают свою природу смертным.');
        $indexer = $this->app->make(LoreIndexer::class);
        $indexer->rebuildApproved($entry);
        $this->assertDatabaseCount('lore_chunks', 2);

        LoreChunk::query()->where('lore_entry_id', $entry->id)->delete();
        $rebuilt = $indexer->rebuildApproved($entry);

        $this->assertSame(2, $rebuilt);
        $this->assertDatabaseHas('lore_chunks', [
            'content' => 'Каиниты не раскрывают свою природу смертным.',
        ]);
    }

    public function test_failed_indexing_does_not_change_canon_or_existing_chunks(): void
    {
        $entry = $this->publishApproved('Маскарад', 'Канон до индексации.');
        $indexer = $this->app->make(LoreIndexer::class);
        $indexer->rebuildApproved($entry);

        $this->app->make(LoreEntryService::class)->publish(
            $entry->chronicle,
            ['canonical_text' => 'Канон после правки.', 'status' => LoreEntryStatus::Approved],
            'v2',
            $entry,
        );

        $this->app->instance(EmbeddingProvider::class, new class implements EmbeddingProvider
        {
            public function embed(string $text): array
            {
                throw new RuntimeException('embedding unavailable');
            }
        });

        try {
            $this->app->make(LoreIndexer::class)->rebuildApproved($entry->fresh());
            $this->fail('Expected embedding failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('embedding unavailable', $e->getMessage());
        }

        $this->assertSame('Канон после правки.', $entry->fresh()->canonical_text);
        $this->assertDatabaseCount('lore_chunks', 2);
        $this->assertDatabaseHas('lore_chunks', ['content' => 'Канон до индексации.']);
    }

    public function test_search_requires_chronicle_and_does_not_leak_across_chronicles(): void
    {
        $prague = $this->publishApproved('Маскарад', 'Каиниты не раскрывают свою природу смертным.');
        $berlin = Chronicle::factory()->create();
        $this->app->make(LoreEntryService::class)->publish($berlin, [
            'title' => 'Саботаж',
            'canonical_text' => 'Анархи рвут Маскарад в Берлине.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');

        $indexer = $this->app->make(LoreIndexer::class);
        $indexer->rebuildApproved($prague);
        $indexer->rebuildApproved(LoreEntry::query()->where('chronicle_id', $berlin->id)->firstOrFail());

        $searcher = $this->app->make(LoreSearcher::class);
        $pragueHits = $searcher->search($prague->chronicle_id, 'Каиниты не раскрывают свою природу смертным.');
        $berlinHits = $searcher->search($berlin->id, 'Каиниты не раскрывают свою природу смертным.');

        $this->assertNotEmpty($pragueHits);
        $this->assertTrue($pragueHits->every(fn (LoreChunk $chunk): bool => (int) $chunk->chronicle_id === (int) $prague->chronicle_id));
        $this->assertTrue($berlinHits->every(fn (LoreChunk $chunk): bool => (int) $chunk->chronicle_id === (int) $berlin->id));
        $this->assertFalse($berlinHits->contains(fn (LoreChunk $chunk): bool => (int) $chunk->lore_entry_id === (int) $prague->id));
    }

    public function test_storyteller_only_chunks_are_hidden_without_visibility_flag(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entry = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Тайна сира',
            'canonical_text' => 'Сир Виктории служит Шабашу.',
            'status' => LoreEntryStatus::Approved,
            'visibility' => LoreVisibility::StorytellerOnly,
        ], 'v1');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);

        $searcher = $this->app->make(LoreSearcher::class);
        $hidden = $searcher->search($chronicle->id, 'Сир Виктории служит Шабашу.', includeStorytellerOnly: false);
        $visible = $searcher->search($chronicle->id, 'Сир Виктории служит Шабашу.', includeStorytellerOnly: true);

        $this->assertTrue($hidden->isEmpty());
        $this->assertNotEmpty($visible);
    }

    public function test_knowledge_filter_limits_to_granted_entries(): void
    {
        $known = $this->publishApproved('Маскарад', 'Каиниты не раскрывают свою природу смертным.');
        $secret = $this->app->make(LoreEntryService::class)->publish($known->chronicle, [
            'title' => 'Тайна',
            'canonical_text' => 'Двор знает о предателе.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');
        $indexer = $this->app->make(LoreIndexer::class);
        $indexer->rebuildApproved($known);
        $indexer->rebuildApproved($secret);

        $searcher = $this->app->make(LoreSearcher::class);
        $none = $searcher->search($known->chronicle_id, 'Двор знает о предателе.', knownLoreEntryIds: []);
        $granted = $searcher->search($known->chronicle_id, 'Каиниты не раскрывают свою природу смертным.', knownLoreEntryIds: [$known->id]);

        $this->assertTrue($none->isEmpty());
        $this->assertTrue($granted->every(fn (LoreChunk $chunk): bool => (int) $chunk->lore_entry_id === (int) $known->id));
        $this->assertFalse($granted->contains(fn (LoreChunk $chunk): bool => (int) $chunk->lore_entry_id === (int) $secret->id));
    }

    public function test_similarity_threshold_drops_unrelated_vector_hits(): void
    {
        $entry = $this->publishApproved('Маскарад', 'Каиниты не раскрывают свою природу смертным.');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);

        $hits = $this->app->make(LoreSearcher::class)->search(
            $entry->chronicle_id,
            'совершенно другой запрос про погоду',
            maxDistance: 0.05,
        );

        $this->assertTrue($hits->isEmpty());
    }

    private function publishApproved(string $title, string $text): LoreEntry
    {
        $chronicle = Chronicle::factory()->create();

        return $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => $title,
            'canonical_text' => $text,
            'status' => LoreEntryStatus::Approved,
        ], 'v1');
    }

    public function test_archived_lore_is_excluded_from_search(): void
    {
        $entry = $this->publishApproved('Маскарад', 'Каиниты не раскрывают свою природу смертным.');
        $this->app->make(LoreIndexer::class)->rebuildApproved($entry);
        $this->app->make(LoreEntryService::class)->archive($entry);

        $hits = $this->app->make(LoreSearcher::class)->search(
            (int) $entry->chronicle_id,
            'Каиниты не раскрывают свою природу смертным.',
        );

        $this->assertTrue($hits->isEmpty());
        $this->assertGreaterThan(0, LoreChunk::query()->where('lore_entry_id', $entry->id)->count());
    }
}
