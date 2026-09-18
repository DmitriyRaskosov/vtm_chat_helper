<?php

namespace Tests\Feature;

use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\User;
use App\Models\WorldRelation;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorldDirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_storyteller_creates_lists_archives_and_restores_directory_entities(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        Sanctum::actingAs($storyteller);

        $faction = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
            'short_description' => 'Башня.',
        ])->assertCreated()->json('entity');

        $this->assertSame('faction', $faction['entity_type']);
        $this->assertSame('active', $faction['status']);

        $this->postJson('/api/world/entities', [
            'canonical_name' => 'Элизиум',
            'entity_type' => 'location',
        ])->assertCreated();

        $list = $this->getJson('/api/world/entities')->assertOk()->json();
        $this->assertCount(2, $list['entities']);
        $this->assertSame([], $list['archived']);

        $this->postJson('/api/world/entities/'.$faction['id'].'/archive')->assertOk()
            ->assertJsonPath('entity.status', 'archived');

        $afterArchive = $this->getJson('/api/world/entities')->assertOk()->json();
        $this->assertCount(1, $afterArchive['entities']);
        $this->assertCount(1, $afterArchive['archived']);

        $this->postJson('/api/world/entities/'.$faction['id'].'/restore')->assertOk()
            ->assertJsonPath('entity.status', 'active');
    }

    public function test_player_cannot_access_world_directory(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/world/entities')->assertForbidden();
        $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
        ])->assertForbidden();
        $this->getJson('/api/world/relations?keys=controls,owns')->assertForbidden();
        $this->postJson('/api/world/relations', [
            'source_entity_id' => 1,
            'target_entity_id' => 2,
            'relation_key' => 'controls',
        ])->assertForbidden();
    }

    public function test_storyteller_manages_directory_controls_and_owns_relations(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $sabbat = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Саббат',
            'entity_type' => 'faction',
        ])->assertCreated()->json('entity');
        $city = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Чикаго',
            'entity_type' => 'location',
        ])->assertCreated()->json('entity');
        $relic = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Клинок',
            'entity_type' => 'item',
        ])->assertCreated()->json('entity');

        $controls = $this->postJson('/api/world/relations', [
            'source_entity_id' => $sabbat['id'],
            'target_entity_id' => $city['id'],
            'relation_key' => 'controls',
        ])->assertCreated()->json('relation');

        $this->assertSame('controls', $controls['relation_key']);
        $this->assertSame($sabbat['id'], $controls['source_entity_id']);
        $this->assertSame($city['id'], $controls['target_entity_id']);
        $this->assertDatabaseCount('world_relations', 1);

        $this->postJson('/api/world/relations', [
            'source_entity_id' => $sabbat['id'],
            'target_entity_id' => $city['id'],
            'relation_key' => 'controls',
        ])->assertOk()->assertJsonPath('relation.id', $controls['id']);

        $owns = $this->postJson('/api/world/relations', [
            'source_entity_id' => $sabbat['id'],
            'target_entity_id' => $relic['id'],
            'relation_key' => 'owns',
        ])->assertCreated()->json('relation');

        $this->assertSame('owns', $owns['relation_key']);

        $list = $this->getJson('/api/world/relations?keys=controls,owns')->assertOk()->json('relations');
        $this->assertCount(2, $list);

        $controlsOnly = $this->getJson('/api/world/relations?keys=controls')->assertOk()->json('relations');
        $this->assertCount(1, $controlsOnly);
        $this->assertSame('controls', $controlsOnly[0]['relation_key']);

        $this->getJson('/api/world/relations?keys=controls,foo')->assertUnprocessable();
        $this->getJson('/api/world/relations')->assertUnprocessable();

        $this->postJson('/api/world/relations/'.$controls['id'].'/end')->assertOk();
        $this->assertSame([], $this->getJson('/api/world/relations?keys=controls')->json('relations'));
        $this->assertCount(1, $this->getJson('/api/world/relations?keys=owns')->json('relations'));
    }

    public function test_unified_relations_accept_politics_and_reject_invalid_pairs(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $camarilla = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
        ])->json('entity');
        $sabbat = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Саббат',
            'entity_type' => 'faction',
        ])->json('entity');
        $place = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Прага',
            'entity_type' => 'location',
        ])->json('entity');

        $this->postJson('/api/world/relations', [
            'source_entity_id' => $camarilla['id'],
            'target_entity_id' => $sabbat['id'],
            'relation_key' => 'allied_with',
        ])->assertCreated();

        $this->postJson('/api/world/relations', [
            'source_entity_id' => $place['id'],
            'target_entity_id' => $camarilla['id'],
            'relation_key' => 'controls',
        ])->assertUnprocessable();

        $politics = $this->postJson('/api/world/relations', [
            'source_entity_id' => $camarilla['id'],
            'target_entity_id' => $sabbat['id'],
            'relation_key' => 'hostile_to',
        ])->assertOk()->json('relation');

        $this->postJson('/api/world/relations/'.$politics['id'].'/end')->assertOk();
    }

    public function test_storyteller_manages_part_of_directory_relations(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $codex = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Кодекс Милана',
            'entity_type' => 'concept',
        ])->assertCreated()->json('entity');
        $article = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Статья I',
            'entity_type' => 'concept',
        ])->assertCreated()->json('entity');

        $partOf = $this->postJson('/api/world/relations', [
            'source_entity_id' => $article['id'],
            'target_entity_id' => $codex['id'],
            'relation_key' => 'part_of',
        ])->assertCreated()->json('relation');

        $this->assertSame('part_of', $partOf['relation_key']);
        $this->assertDatabaseCount('world_relations', 1);

        $this->postJson('/api/world/relations', [
            'source_entity_id' => $article['id'],
            'target_entity_id' => $codex['id'],
            'relation_key' => 'part_of',
        ])->assertOk()->assertJsonPath('relation.id', $partOf['id']);

        $list = $this->getJson('/api/world/relations?keys=part_of')->assertOk()->json('relations');
        $this->assertCount(1, $list);
    }

    public function test_directory_rejects_characters_and_invalid_entity_type(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $this->postJson('/api/world/entities', [
            'canonical_name' => 'Виктория',
            'entity_type' => 'character',
        ])->assertUnprocessable();

        $this->postJson('/api/world/entities', [
            'canonical_name' => 'Гильдия воров',
            'entity_type' => 'guild',
        ])->assertUnprocessable();

        $chronicle = Chronicle::query()->orderBy('id')->firstOrFail();
        $character = $this->app->make(WorldEntityService::class)
            ->create($chronicle, WorldEntityType::Character, 'Виктория');

        $this->postJson('/api/world/entities/'.$character->id.'/archive')->assertUnprocessable();
    }

    public function test_storyteller_creates_circle_entity(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $circle = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Круг Примогенов',
            'entity_type' => 'circle',
        ])->assertCreated()->json('entity');

        $this->assertSame('circle', $circle['entity_type']);
    }

    public function test_storyteller_sets_and_clears_parent_faction(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $camarilla = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
        ])->assertCreated()->json('entity');

        $chapter = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Пражская камарилья',
            'entity_type' => 'faction',
            'parent_faction_id' => $camarilla['id'],
        ])->assertCreated()->json('entity');

        $this->assertSame($camarilla['id'], $chapter['parent_faction_id']);

        $this->putJson('/api/world/entities/'.$chapter['id'], [
            'canonical_name' => 'Пражская камарилья',
            'parent_faction_id' => null,
        ])->assertOk()->assertJsonPath('entity.parent_faction_id', null);

        $this->putJson('/api/world/entities/'.$camarilla['id'], [
            'canonical_name' => 'Камарилья',
            'parent_faction_id' => $camarilla['id'],
        ])->assertUnprocessable();

        $this->postJson('/api/world/entities', [
            'canonical_name' => 'Элизиум',
            'entity_type' => 'location',
            'parent_faction_id' => $camarilla['id'],
        ])->assertUnprocessable();
    }

    public function test_faction_politics_are_symmetric_and_mutually_exclusive(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $camarilla = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
        ])->assertCreated()->json('entity');
        $sabbat = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Саббат',
            'entity_type' => 'faction',
        ])->assertCreated()->json('entity');

        $hostile = $this->postJson('/api/world/relations', [
            'source_entity_id' => $camarilla['id'],
            'target_entity_id' => $sabbat['id'],
            'relation_key' => 'hostile_to',
        ])->assertCreated()->json('relation');

        $this->assertSame('hostile_to', $hostile['relation_key']);
        $this->assertDatabaseCount('world_relations', 1);

        $this->postJson('/api/world/relations', [
            'source_entity_id' => $sabbat['id'],
            'target_entity_id' => $camarilla['id'],
            'relation_key' => 'hostile_to',
        ])->assertOk()->assertJsonPath('relation.id', $hostile['id']);

        $allied = $this->postJson('/api/world/relations', [
            'source_entity_id' => $camarilla['id'],
            'target_entity_id' => $sabbat['id'],
            'relation_key' => 'allied_with',
        ])->assertCreated()->json('relation');

        $this->assertNotSame($hostile['id'], $allied['id']);
        $this->assertNotNull(WorldRelation::query()->findOrFail($hostile['id'])->valid_to);
        $this->assertSame(1, WorldRelation::query()->active()->count());

        $list = $this->getJson('/api/world/relations?keys=hostile_to,allied_with')->assertOk()->json('relations');
        $this->assertCount(1, $list);
        $this->assertSame('allied_with', $list[0]['relation_key']);

        $this->postJson('/api/world/relations/'.$allied['id'].'/end')->assertOk();
        $this->assertSame([], $this->getJson('/api/world/relations?keys=hostile_to,allied_with')->json('relations'));
    }

    public function test_faction_politics_reject_non_factions(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $faction = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
        ])->json('entity');
        $place = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Прага',
            'entity_type' => 'location',
        ])->json('entity');

        $this->postJson('/api/world/relations', [
            'source_entity_id' => $faction['id'],
            'target_entity_id' => $place['id'],
            'relation_key' => 'hostile_to',
        ])->assertUnprocessable();
    }

    public function test_storyteller_updates_directory_entity(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $faction = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
            'short_description' => 'Башня.',
        ])->assertCreated()->json('entity');

        $this->putJson('/api/world/entities/'.$faction['id'], [
            'canonical_name' => 'Новая Камарилья',
            'short_description' => 'Обновлённое описание.',
        ])->assertOk()
            ->assertJsonPath('entity.canonical_name', 'Новая Камарилья')
            ->assertJsonPath('entity.short_description', 'Обновлённое описание.')
            ->assertJsonPath('entity.entity_type', 'faction');
    }

    public function test_storyteller_manages_directory_aliases(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $entity = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Котерия Гангрелов',
            'entity_type' => 'coterie',
            'aliases' => ['Gangrel Coterie', 'Гангрелы'],
        ])->assertCreated()->json('entity');

        $this->assertSame(['Gangrel Coterie', 'Гангрелы'], $entity['aliases']);

        $this->putJson('/api/world/entities/'.$entity['id'], [
            'canonical_name' => 'Котерия Гангрелов',
            'aliases' => ['Gangrel Coterie'],
        ])->assertOk()
            ->assertJsonPath('entity.aliases', ['Gangrel Coterie']);

        $this->assertDatabaseMissing('world_entity_aliases', [
            'entity_id' => $entity['id'],
            'alias' => 'Гангрелы',
        ]);
        $this->getJson('/api/world/entities')->assertOk()
            ->assertJsonPath('entities.0.aliases', ['Gangrel Coterie']);
    }

    public function test_player_cannot_update_directory_entity(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $faction = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
        ])->json('entity');

        Sanctum::actingAs(User::factory()->create());
        $this->putJson('/api/world/entities/'.$faction['id'], [
            'canonical_name' => 'Чужое имя',
        ])->assertForbidden();
    }

    public function test_archived_directory_entity_cannot_be_updated(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $faction = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
        ])->json('entity');
        $this->postJson('/api/world/entities/'.$faction['id'].'/archive')->assertOk();

        $this->putJson('/api/world/entities/'.$faction['id'], [
            'canonical_name' => 'Новое имя',
        ])->assertUnprocessable();
    }

    public function test_storyteller_sets_character_place_in_the_world(): void
    {
        $this->markTestSkipped('MVP: PUT /characters/{id}/place is frozen.');
        $storyteller = User::factory()->storyteller()->create();
        $player = User::factory()->create();
        Sanctum::actingAs($storyteller);
        $chronicle = Chronicle::query()->orderBy('id')->firstOrFail();
        $entities = $this->app->make(WorldEntityService::class);

        $pc = $this->postJson('/api/characters', [
            'canonical_name' => 'Анна',
            'character_type' => CharacterType::Player->value,
            'user_id' => $player->id,
        ])->assertCreated()->json('character');

        $camarilla = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
        ])->json('entity');
        $anarchs = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Анархи',
            'entity_type' => 'faction',
        ])->json('entity');
        $ventrue = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Вентру',
            'entity_type' => 'clan',
        ])->json('entity');
        $haven = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Гавань',
            'entity_type' => 'location',
        ])->json('entity');
        $coterie = $entities->create(
            $chronicle,
            WorldEntityType::Coterie,
            'Котерия Праги',
        );
        $this->app->make(CharacterAffiliationService::class)->attach(
            Character::query()->findOrFail($pc['id']),
            $coterie,
            CharacterAffiliationType::Member,
            CharacterAffiliationStance::Allied,
        );

        $sheet = $this->putJson('/api/characters/'.$pc['id'].'/place', [
            'sect_entity_id' => $camarilla['id'],
            'clan_entity_id' => $ventrue['id'],
            'haven_entity_id' => $haven['id'],
        ])->assertOk()->json('character');

        $this->assertSame($camarilla['id'], $sheet['sect_entity_id']);
        $this->assertSame('Камарилья', $sheet['sect_name']);
        $this->assertSame($ventrue['id'], $sheet['clan_entity_id']);
        $this->assertSame('Вентру', $sheet['clan_name']);
        $this->assertSame($haven['id'], $sheet['haven_entity_id']);
        $this->assertSame('Гавань', $sheet['haven_name']);
        $this->assertSame([0], $sheet['lore_clearance_levels']);

        $this->putJson('/api/characters/'.$pc['id'].'/place', [
            'sect_entity_id' => $anarchs['id'],
            'clan_entity_id' => $ventrue['id'],
            'haven_entity_id' => $haven['id'],
            'lore_clearance_levels' => [0, 1, 2],
        ])->assertOk()
            ->assertJsonPath('character.sect_name', 'Анархи')
            ->assertJsonPath('character.lore_clearance_levels', [0, 1, 2]);

        $this->assertTrue(
            WorldRelation::query()->where('source_entity_id', $pc['id'])
                ->where('target_entity_id', $coterie->id)
                ->active()
                ->exists(),
        );

        $this->putJson('/api/characters/'.$pc['id'].'/place', [
            'sect_entity_id' => $ventrue['id'],
            'clan_entity_id' => $ventrue['id'],
            'haven_entity_id' => $haven['id'],
        ])->assertUnprocessable();

        Sanctum::actingAs($player);
        $own = $this->getJson('/api/characters/'.$pc['id'])->assertOk()->json('character');
        $this->assertSame('Анархи', $own['sect_name']);
        $this->assertSame([0, 1, 2], $own['lore_clearance_levels']);
        $this->putJson('/api/characters/'.$pc['id'].'/place', [
            'sect_entity_id' => $camarilla['id'],
            'clan_entity_id' => $ventrue['id'],
            'haven_entity_id' => $haven['id'],
        ])->assertForbidden();
    }
}
