<?php

namespace Tests\Feature;

use App\Character\CharacterAffiliationService;
use App\Enums\CharacterAffiliationStance;
use App\Enums\CharacterAffiliationType;
use App\Enums\CharacterType;
use App\Enums\FactionType;
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
            'subtype' => 'sect',
            'short_description' => 'Башня.',
        ])->assertCreated()->json('entity');

        $this->assertSame('sect', $faction['subtype']);
        $this->assertSame('active', $faction['status']);

        $this->postJson('/api/world/entities', [
            'canonical_name' => 'Элизиум',
            'entity_type' => 'location',
            'subtype' => 'site',
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
    }

    public function test_directory_rejects_characters_and_invalid_subtype(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $this->postJson('/api/world/entities', [
            'canonical_name' => 'Виктория',
            'entity_type' => 'character',
        ])->assertUnprocessable();

        $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
            'subtype' => 'site',
        ])->assertUnprocessable();

        $chronicle = Chronicle::query()->orderBy('id')->firstOrFail();
        $character = $this->app->make(WorldEntityService::class)
            ->create($chronicle, WorldEntityType::Character, 'Виктория');

        $this->postJson('/api/world/entities/'.$character->id.'/archive')->assertUnprocessable();
    }

    public function test_faction_politics_are_symmetric_and_mutually_exclusive(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $camarilla = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
            'subtype' => 'sect',
        ])->assertCreated()->json('entity');
        $sabbat = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Саббат',
            'entity_type' => 'faction',
            'subtype' => 'sect',
        ])->assertCreated()->json('entity');

        $hostile = $this->postJson('/api/world/faction-relations', [
            'source_entity_id' => $camarilla['id'],
            'target_entity_id' => $sabbat['id'],
            'relation_key' => 'hostile_to',
        ])->assertCreated()->json('relation');

        $this->assertSame('hostile_to', $hostile['relation_key']);
        $this->assertDatabaseCount('world_relations', 1);

        $this->postJson('/api/world/faction-relations', [
            'source_entity_id' => $sabbat['id'],
            'target_entity_id' => $camarilla['id'],
            'relation_key' => 'hostile_to',
        ])->assertOk()->assertJsonPath('relation.id', $hostile['id']);

        $allied = $this->postJson('/api/world/faction-relations', [
            'source_entity_id' => $camarilla['id'],
            'target_entity_id' => $sabbat['id'],
            'relation_key' => 'allied_with',
        ])->assertCreated()->json('relation');

        $this->assertNotSame($hostile['id'], $allied['id']);
        $this->assertNotNull(WorldRelation::query()->findOrFail($hostile['id'])->ended_at);
        $this->assertSame(1, WorldRelation::query()->active()->count());

        $list = $this->getJson('/api/world/faction-relations')->assertOk()->json('relations');
        $this->assertCount(1, $list);
        $this->assertSame('allied_with', $list[0]['relation_key']);

        $this->postJson('/api/world/faction-relations/'.$allied['id'].'/end')->assertOk();
        $this->assertSame([], $this->getJson('/api/world/faction-relations')->json('relations'));
    }

    public function test_faction_politics_reject_non_factions(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $faction = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
            'subtype' => 'sect',
        ])->json('entity');
        $place = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Прага',
            'entity_type' => 'location',
        ])->json('entity');

        $this->postJson('/api/world/faction-relations', [
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
            'subtype' => 'sect',
            'short_description' => 'Башня.',
        ])->assertCreated()->json('entity');

        $this->putJson('/api/world/entities/'.$faction['id'], [
            'canonical_name' => 'Новая Камарилья',
            'short_description' => 'Обновлённое описание.',
            'subtype' => 'guild',
        ])->assertOk()
            ->assertJsonPath('entity.canonical_name', 'Новая Камарилья')
            ->assertJsonPath('entity.short_description', 'Обновлённое описание.')
            ->assertJsonPath('entity.subtype', 'guild');
    }

    public function test_player_cannot_update_directory_entity(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());
        $faction = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Камарилья',
            'entity_type' => 'faction',
            'subtype' => 'sect',
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
            'subtype' => 'sect',
        ])->json('entity');
        $this->postJson('/api/world/entities/'.$faction['id'].'/archive')->assertOk();

        $this->putJson('/api/world/entities/'.$faction['id'], [
            'canonical_name' => 'Новое имя',
        ])->assertUnprocessable();
    }

    public function test_storyteller_sets_character_place_in_the_world(): void
    {
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
            'subtype' => 'sect',
        ])->json('entity');
        $anarchs = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Анархи',
            'entity_type' => 'faction',
            'subtype' => 'sect',
        ])->json('entity');
        $ventrue = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Вентру',
            'entity_type' => 'faction',
            'subtype' => 'clan',
        ])->json('entity');
        $haven = $this->postJson('/api/world/entities', [
            'canonical_name' => 'Гавань',
            'entity_type' => 'location',
            'subtype' => 'site',
        ])->json('entity');
        $coterie = $entities->create(
            $chronicle,
            WorldEntityType::Faction,
            'Котерия Праги',
            typed: ['faction_type' => FactionType::Coterie],
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
