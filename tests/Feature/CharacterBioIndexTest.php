<?php

namespace Tests\Feature;

use App\Character\CharacterBiographyService;
use App\Character\CharacterBioIndexer;
use App\Character\CharacterBioSearcher;
use App\Enums\CharacterBiographySection;
use App\Models\Character;
use App\Models\CharacterBioChunk;
use App\Models\CharacterBiographyVersion;
use App\Rag\EmbeddingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class CharacterBioIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_does_not_index_until_a_saved_version_is_indexed(): void
    {
        $character = Character::factory()->create();
        $this->app->make(CharacterBiographyService::class)
            ->publish($character, ['summary' => 'Виктория служит Камарилье.'], 'v1');

        $this->assertDatabaseCount('character_bio_chunks', 0);
    }

    public function test_indexer_writes_section_chunks_to_the_dedicated_corpus(): void
    {
        $character = Character::factory()->create();
        $this->app->make(CharacterBiographyService::class)->publish(
            $character,
            [
                'summary' => 'Виктория служит Камарилье в Праге.',
                'full_text' => 'Она хранит Маскарад.',
                'principles' => 'Не раскрывать природу смертным.',
            ],
            'v1',
        );

        $version = CharacterBiographyVersion::query()->firstOrFail();
        $count = $this->app->make(CharacterBioIndexer::class)->indexVersion($version);

        $this->assertSame(3, $count);
        $this->assertDatabaseCount('character_bio_chunks', 3);
        $this->assertDatabaseHas('character_bio_chunks', [
            'character_id' => $character->id,
            'biography_version_id' => $version->id,
            'section' => CharacterBiographySection::Summary->value,
            'content' => 'Виктория служит Камарилье в Праге.',
        ]);
    }

    public function test_reindex_is_idempotent_and_replaces_previous_version_chunks(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterBiographyService::class);
        $indexer = $this->app->make(CharacterBioIndexer::class);

        $service->publish($character, ['summary' => 'Первая версия канона.'], 'v1');
        $first = CharacterBiographyVersion::query()->where('version', 1)->firstOrFail();
        $indexer->indexVersion($first);

        $service->publish($character, ['summary' => 'Вторая версия канона.'], 'v2');
        $second = CharacterBiographyVersion::query()->where('version', 2)->firstOrFail();
        $indexer->indexVersion($second);
        $again = $indexer->indexVersion($second);

        $this->assertSame(1, $again);
        $this->assertDatabaseCount('character_bio_chunks', 1);
        $this->assertDatabaseHas('character_bio_chunks', [
            'character_id' => $character->id,
            'biography_version_id' => $second->id,
            'content' => 'Вторая версия канона.',
        ]);
        $this->assertDatabaseMissing('character_bio_chunks', [
            'biography_version_id' => $first->id,
        ]);
    }

    public function test_failed_indexing_does_not_change_canon_or_existing_chunks(): void
    {
        $character = Character::factory()->create();
        $service = $this->app->make(CharacterBiographyService::class);
        $service->publish($character, ['summary' => 'Канон до индексации.'], 'v1');
        $version = CharacterBiographyVersion::query()->firstOrFail();

        $this->app->make(CharacterBioIndexer::class)->indexVersion($version);
        $this->assertDatabaseCount('character_bio_chunks', 1);

        $service->publish($character, ['summary' => 'Канон после правки.'], 'v2');
        $this->assertSame('Канон после правки.', $service->current($character)?->summary);

        $this->app->instance(EmbeddingProvider::class, new class implements EmbeddingProvider
        {
            public function embed(string $text): array
            {
                throw new RuntimeException('embedding unavailable');
            }
        });

        $second = CharacterBiographyVersion::query()->where('version', 2)->firstOrFail();

        try {
            $this->app->make(CharacterBioIndexer::class)->indexVersion($second);
            $this->fail('Expected embedding failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('embedding unavailable', $e->getMessage());
        }

        $this->assertSame('Канон после правки.', $service->current($character)?->fresh()->summary);
        $this->assertDatabaseCount('character_bio_chunks', 1);
        $this->assertDatabaseHas('character_bio_chunks', [
            'content' => 'Канон до индексации.',
            'biography_version_id' => $version->id,
        ]);
    }

    public function test_index_can_be_dropped_and_rebuilt_from_canonical_version(): void
    {
        $character = Character::factory()->create();
        $this->app->make(CharacterBiographyService::class)->publish(
            $character,
            [
                'summary' => 'Кратко.',
                'full_text' => 'Полный канонический текст.',
            ],
            'v1',
        );

        $indexer = $this->app->make(CharacterBioIndexer::class);
        $indexer->rebuildForCharacter($character);
        $this->assertDatabaseCount('character_bio_chunks', 2);

        CharacterBioChunk::query()->where('character_id', $character->id)->delete();
        $this->assertDatabaseCount('character_bio_chunks', 0);

        $rebuilt = $indexer->rebuildForCharacter($character);
        $this->assertSame(2, $rebuilt);
        $this->assertDatabaseHas('character_bio_chunks', [
            'character_id' => $character->id,
            'section' => CharacterBiographySection::FullText->value,
            'content' => 'Полный канонический текст.',
        ]);
    }

    public function test_search_always_filters_by_character_before_top_k(): void
    {
        $alice = Character::factory()->create();
        $bob = Character::factory()->create();
        $service = $this->app->make(CharacterBiographyService::class);
        $indexer = $this->app->make(CharacterBioIndexer::class);

        $service->publish($alice, ['summary' => 'Виктория служит Камарилье в Праге.'], 'v1');
        $service->publish($bob, ['summary' => 'Маркус охотится в Берлине ночами.'], 'v1');
        $indexer->rebuildForCharacter($alice);
        $indexer->rebuildForCharacter($bob);

        $searcher = $this->app->make(CharacterBioSearcher::class);
        $aliceHits = $searcher->search($alice->id, 'Виктория служит Камарилье в Праге.');
        $leaked = $searcher->search($bob->id, 'Виктория служит Камарилье в Праге.');

        $this->assertNotEmpty($aliceHits);
        $this->assertTrue($aliceHits->every(fn (CharacterBioChunk $chunk): bool => (int) $chunk->character_id === (int) $alice->id));
        $this->assertTrue($leaked->every(fn (CharacterBioChunk $chunk): bool => (int) $chunk->character_id === (int) $bob->id));
        $this->assertFalse($leaked->contains(fn (CharacterBioChunk $chunk): bool => (int) $chunk->character_id === (int) $alice->id));
    }

    public function test_long_full_text_is_split_into_multiple_chunks(): void
    {
        $paragraph = str_repeat('Каинит хранит Маскарад. ', 80);
        $character = Character::factory()->create();
        $this->app->make(CharacterBiographyService::class)->publish(
            $character,
            [
                'summary' => 'Кратко.',
                'full_text' => $paragraph."\n\n".$paragraph,
            ],
            'v1',
        );

        $count = $this->app->make(CharacterBioIndexer::class)->rebuildForCharacter($character);

        $this->assertGreaterThanOrEqual(3, $count);
        $this->assertSame(
            2,
            CharacterBioChunk::query()
                ->where('section', CharacterBiographySection::FullText)
                ->count(),
        );
    }
}
