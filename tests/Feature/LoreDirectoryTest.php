<?php

namespace Tests\Feature;

use App\Enums\CharacterType;
use App\Enums\LoreAccessLevel;
use App\Enums\LoreEntryKind;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Enums\WorldEntityType;
use App\Lore\LoreSearcher;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\LoreChunk;
use App\Models\User;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LoreDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_storyteller_publishes_lore_with_entities_and_exceptions(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $player = User::factory()->create();
        Sanctum::actingAs($storyteller);

        $npc = $this->postJson('/api/characters', [
            'canonical_name' => 'Виктория',
            'character_type' => CharacterType::Npc->value,
        ])->assertCreated()->json('character');
        $other = $this->postJson('/api/characters', [
            'canonical_name' => 'Маркус',
            'character_type' => CharacterType::Npc->value,
        ])->assertCreated()->json('character');
        $camarilla = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
            'subtype' => 'sect',
        ])->assertCreated()->json('entity');

        $created = $this->postJson('/api/lore', [
            'title' => 'Маскарад',
            'kind' => LoreEntryKind::Custom->value,
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'visibility' => LoreVisibility::StorytellerOnly->value,
            'classification' => LoreAccessLevel::L2->value,
            'entity_ids' => [$camarilla['id'], $npc['id']],
            'granted_character_ids' => [$npc['id']],
        ])->assertCreated()->json('lore');

        $this->assertSame('Маскарад', $created['title']);
        $this->assertSame(LoreEntryStatus::Approved->value, $created['status']);
        $this->assertSame(LoreAccessLevel::L2->value, $created['classification']);
        $this->assertSame(1, $created['current_version']);
        $this->assertEqualsCanonicalizing([$camarilla['id'], $npc['id']], $created['entity_ids']);
        $this->assertSame([$npc['id']], $created['granted_character_ids']);
        $this->assertSame([], $created['denied_character_ids']);
        $this->assertDatabaseCount('lore_chunks', 2);
        $this->assertDatabaseHas('lore_entry_versions', [
            'lore_entry_id' => $created['id'],
            'classification' => LoreAccessLevel::L2->value,
            'change_reason' => 'Правка с экрана мира',
        ]);

        $searcher = $this->app->make(LoreSearcher::class);
        $victoria = Character::query()->findOrFail($npc['id']);
        $marcus = Character::query()->findOrFail($other['id']);
        $this->assertNotEmpty($searcher->searchForCharacter($victoria, 'Каиниты не раскрывают свою природу смертным.'));
        $this->assertTrue($searcher->searchForCharacter($marcus, 'Каиниты не раскрывают свою природу смертным.')->isEmpty());

        $this->putJson('/api/lore/'.$created['id'], [
            'title' => 'Маскарад',
            'kind' => LoreEntryKind::Custom->value,
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'visibility' => LoreVisibility::StorytellerOnly->value,
            'classification' => LoreAccessLevel::L2->value,
            'entity_ids' => [$camarilla['id'], $npc['id']],
            'granted_character_ids' => [$npc['id']],
        ])->assertOk()->assertJsonPath('lore.current_version', 1);

        $this->putJson('/api/lore/'.$created['id'], [
            'title' => 'Маскарад',
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'visibility' => LoreVisibility::Public->value,
            'entity_ids' => [$camarilla['id']],
            'granted_character_ids' => [],
        ])->assertOk()
            ->assertJsonPath('lore.current_version', 2)
            ->assertJsonPath('lore.granted_character_ids', [])
            ->assertJsonPath('lore.entity_ids', [$camarilla['id']]);

        $this->assertTrue($searcher->searchForCharacter($victoria->fresh(), 'Каиниты не раскрывают свою природу смертным.')->isEmpty());

        $list = $this->getJson('/api/lore')->assertOk()->json();
        $this->assertCount(1, $list['lore']);
        $this->assertSame([], $list['archived']);
        $this->assertSame(LoreAccessLevel::L2->value, $list['lore'][0]['classification']);
        $this->assertArrayNotHasKey('canonical_text', $list['lore'][0]);

        $this->postJson('/api/lore/'.$created['id'].'/archive')->assertOk()
            ->assertJsonPath('lore.status', LoreEntryStatus::Archived->value);
        $this->assertDatabaseCount('lore_chunks', 0);

        $afterArchive = $this->getJson('/api/lore')->assertOk()->json();
        $this->assertSame([], $afterArchive['lore']);
        $this->assertCount(1, $afterArchive['archived']);

        $this->postJson('/api/lore/'.$created['id'].'/restore')->assertOk()
            ->assertJsonPath('lore.status', LoreEntryStatus::Approved->value);
        $this->assertGreaterThan(0, LoreChunk::query()->count());

        Sanctum::actingAs($player);
        $this->getJson('/api/lore')->assertForbidden();
        $this->postJson('/api/lore', [
            'title' => 'Чужое',
            'canonical_text' => 'Нет.',
        ])->assertForbidden();
    }

    public function test_player_cannot_read_lore_show(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $entry = $this->postJson('/api/lore', [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
        ])->assertCreated()->json('lore');

        Sanctum::actingAs(User::factory()->create());
        $this->getJson('/api/lore/'.$entry['id'])->assertForbidden();
    }

    public function test_table_lore_is_searchable_without_exceptions(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $npc = $this->postJson('/api/characters', [
            'canonical_name' => 'Виктория',
            'character_type' => CharacterType::Npc->value,
        ])->assertCreated()->json('character');

        $this->postJson('/api/lore', [
            'title' => 'Маскарад',
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'visibility' => LoreVisibility::Public->value,
        ])->assertCreated()->assertJsonPath('lore.classification', LoreAccessLevel::L0->value);

        $hits = $this->app->make(LoreSearcher::class)->searchForCharacter(
            Character::query()->findOrFail($npc['id']),
            'Каиниты не раскрывают свою природу смертным.',
        );
        $this->assertNotEmpty($hits);
    }

    public function test_deny_exception_hides_table_lore_from_that_character(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $npc = $this->postJson('/api/characters', [
            'canonical_name' => 'Виктория',
            'character_type' => CharacterType::Npc->value,
        ])->assertCreated()->json('character');
        $other = $this->postJson('/api/characters', [
            'canonical_name' => 'Маркус',
            'character_type' => CharacterType::Npc->value,
        ])->assertCreated()->json('character');

        $this->postJson('/api/lore', [
            'title' => 'Маскарад',
            'canonical_text' => 'Каиниты не раскрывают свою природу смертным.',
            'denied_character_ids' => [$npc['id']],
        ])->assertCreated()->assertJsonPath('lore.denied_character_ids', [$npc['id']]);

        $searcher = $this->app->make(LoreSearcher::class);
        $this->assertTrue($searcher->searchForCharacter(
            Character::query()->findOrFail($npc['id']),
            'Каиниты не раскрывают свою природу смертным.',
        )->isEmpty());
        $this->assertNotEmpty($searcher->searchForCharacter(
            Character::query()->findOrFail($other['id']),
            'Каиниты не раскрывают свою природу смертным.',
        ));
    }

    public function test_overlap_of_grant_and_deny_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $npc = $this->postJson('/api/characters', [
            'canonical_name' => 'Виктория',
            'character_type' => CharacterType::Npc->value,
        ])->assertCreated()->json('character');

        $this->postJson('/api/lore', [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
            'granted_character_ids' => [$npc['id']],
            'denied_character_ids' => [$npc['id']],
        ])->assertUnprocessable();
    }

    public function test_omitting_links_on_update_keeps_exceptions(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $npc = $this->postJson('/api/characters', [
            'canonical_name' => 'Виктория',
            'character_type' => CharacterType::Npc->value,
        ])->assertCreated()->json('character');
        $camarilla = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
            'subtype' => 'sect',
        ])->assertCreated()->json('entity');

        $created = $this->postJson('/api/lore', [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
            'entity_ids' => [$camarilla['id']],
            'granted_character_ids' => [$npc['id']],
            'denied_character_ids' => [],
        ])->assertCreated()->json('lore');

        $this->putJson('/api/lore/'.$created['id'], [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
        ])->assertOk()
            ->assertJsonPath('lore.granted_character_ids', [$npc['id']])
            ->assertJsonPath('lore.entity_ids', [$camarilla['id']]);
    }

    public function test_lore_rejects_entities_from_another_chronicle(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $foreign = $this->app->make(WorldEntityService::class)->create(
            Chronicle::factory()->create(),
            WorldEntityType::Faction,
            'Чужая секта',
        );

        $this->postJson('/api/lore', [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
            'entity_ids' => [$foreign->id],
        ])->assertConflict();
    }

    public function test_lore_rejects_characters_from_another_chronicle(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $foreign = $this->app->make(WorldEntityService::class)->create(
            Chronicle::factory()->create(),
            WorldEntityType::Character,
            'Чужая',
        );

        $this->postJson('/api/lore', [
            'title' => 'Маскарад',
            'canonical_text' => 'Канон.',
            'granted_character_ids' => [$foreign->id],
        ])->assertConflict();
    }
}
