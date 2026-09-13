<?php

namespace Tests\Feature;

use App\Enums\LoreAccessLevel;
use App\Enums\LoreEntryKind;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Enums\WorldEntityType;
use App\Lore\CannotDeleteLoreEntryException;
use App\Lore\LoreEntryService;
use App\Lore\LoreVersionImmutableException;
use App\Models\Chronicle;
use App\Models\LoreEntry;
use App\Models\LoreEntryVersion;
use App\Models\User;
use App\Models\WorldEntity;
use App\World\MixedChronicleException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\TestCase;

class LoreEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_publish_writes_current_row_and_immutable_version_outside_rag_chunks(): void
    {
        $chronicle = Chronicle::factory()->create();
        $author = User::factory()->storyteller()->create();
        $service = $this->app->make(LoreEntryService::class);

        $entry = $service->publish(
            $chronicle,
            [
                'title' => 'Маскарад',
                'kind' => LoreEntryKind::Custom,
                'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
                'status' => LoreEntryStatus::Approved,
                'visibility' => LoreVisibility::Public,
            ],
            'Первая каноническая версия.',
            author: $author,
        );

        $this->assertSame(1, $entry->current_version);
        $this->assertSame(LoreEntryStatus::Approved, $entry->status);
        $this->assertSame($author->id, $entry->approved_by);
        $this->assertNotNull($entry->approved_at);
        $this->assertDatabaseCount('lore_entry_versions', 1);
        $this->assertDatabaseHas('lore_entry_versions', [
            'lore_entry_id' => $entry->id,
            'version' => 1,
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'change_reason' => 'Первая каноническая версия.',
            'created_by' => $author->id,
        ]);
        $this->assertFalse(Schema::hasTable('rag_chunks'));
    }

    public function test_second_publish_increments_version_and_keeps_previous_snapshot(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(LoreEntryService::class);
        $entry = $service->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Черновик.',
        ], 'v1');

        $entry = $service->publish(
            $chronicle,
            ['canonical_text' => 'Уточнённый канон.'],
            'Правка текста.',
            $entry,
        );

        $this->assertSame(2, $entry->current_version);
        $this->assertSame('Уточнённый канон.', $entry->canonical_text);
        $this->assertDatabaseCount('lore_entry_versions', 2);

        $first = LoreEntryVersion::query()->where('version', 1)->firstOrFail();
        $this->assertSame('Черновик.', $first->canonical_text);

        $second = LoreEntryVersion::query()->where('version', 2)->firstOrFail();
        $this->assertSame('Уточнённый канон.', $second->canonical_text);
        $this->assertSame('Правка текста.', $second->change_reason);
    }

    public function test_version_row_cannot_be_updated_through_eloquent_or_sql(): void
    {
        $chronicle = Chronicle::factory()->create();
        $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
        ], 'v1');

        $version = LoreEntryVersion::query()->firstOrFail();

        try {
            $version->update(['canonical_text' => 'Переписано.']);
            $this->fail('Expected LoreVersionImmutableException.');
        } catch (LoreVersionImmutableException) {
        }

        $this->expectException(QueryException::class);
        DB::table('lore_entry_versions')->where('id', $version->id)->update([
            'canonical_text' => 'SQL rewrite.',
        ]);
    }

    public function test_attach_entity_requires_the_same_chronicle(): void
    {
        $prague = Chronicle::factory()->create();
        $berlin = Chronicle::factory()->create();
        $service = $this->app->make(LoreEntryService::class);
        $entry = $service->publish($prague, [
            'title' => 'Элизиум',
            'kind' => LoreEntryKind::Place,
            'canonical_text' => 'Двор Праги собирается в опере.',
        ], 'v1');

        $location = WorldEntity::factory()->create([
            'chronicle_id' => $prague->id,
            'entity_type' => WorldEntityType::Location,
            'canonical_name' => 'Пражская опера',
        ]);
        $foreign = WorldEntity::factory()->create([
            'chronicle_id' => $berlin->id,
            'canonical_name' => 'Чужой клуб',
        ]);

        $link = $service->attachEntity($entry, $location, 'subject');
        $this->assertSame($location->id, $link->entity_id);
        $this->assertTrue($entry->entities()->whereKey($location->id)->exists());

        $this->expectException(MixedChronicleException::class);
        $service->attachEntity($entry, $foreign);
    }

    public function test_lore_entry_is_the_only_canonical_lore_storage(): void
    {
        $chronicle = Chronicle::factory()->create();
        $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'legacy_source_id' => 'masquerade',
        ], 'v1');

        $this->assertDatabaseCount('lore_entries', 1);
        $this->assertFalse(Schema::hasTable('rag_chunks'));
    }

    public function test_publish_requires_reason_and_body(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(LoreEntryService::class);

        try {
            $service->publish($chronicle, [
                'title' => 'Маскарад',
                'canonical_text' => 'Текст.',
            ], '   ');
            $this->fail('Expected InvalidArgumentException.');
        } catch (InvalidArgumentException $e) {
            $this->assertSame('A change reason is required.', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $service->publish($chronicle, ['title' => 'Только заголовок.'], 'v1');
    }

    public function test_factory_can_create_a_minimal_entry(): void
    {
        $entry = LoreEntry::factory()->create();

        $this->assertDatabaseHas('lore_entries', [
            'id' => $entry->id,
            'current_version' => 1,
        ]);
    }

    public function test_lore_is_archived_instead_of_deleted(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(LoreEntryService::class);
        $entry = $service->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');

        $archived = $service->archive($entry);
        $this->assertSame(LoreEntryStatus::Archived, $archived->status);
        $this->assertSame('Канон.', $archived->canonical_text);
        $this->assertDatabaseCount('lore_entry_versions', 1);

        try {
            $archived->delete();
            $this->fail('Expected CannotDeleteLoreEntryException.');
        } catch (CannotDeleteLoreEntryException) {
        }

        $this->expectException(QueryException::class);
        DB::table('lore_entries')->where('id', $archived->id)->delete();
    }

    public function test_restore_returns_archived_lore_to_approved(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(LoreEntryService::class);
        $entry = $service->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');

        $service->archive($entry);
        $restored = $service->restore($entry->fresh());

        $this->assertSame(LoreEntryStatus::Approved, $restored->status);
        $this->assertSame('Канон.', $restored->canonical_text);
    }

    public function test_classification_change_creates_a_new_version(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(LoreEntryService::class);
        $entry = $service->publish($chronicle, [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');

        $this->assertSame(LoreAccessLevel::L0, $entry->classification);

        $entry = $service->publish(
            $chronicle,
            ['classification' => LoreAccessLevel::L2],
            'Подняли гриф.',
            $entry,
        );

        $this->assertSame(2, $entry->current_version);
        $this->assertSame(LoreAccessLevel::L2, $entry->classification);
        $this->assertDatabaseHas('lore_entry_versions', [
            'lore_entry_id' => $entry->id,
            'version' => 2,
            'classification' => LoreAccessLevel::L2->value,
        ]);
    }
}
