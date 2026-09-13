<?php

namespace Tests\Feature;

use App\Enums\CharacterHealthState;
use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\Discipline;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CharacterSheetTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_includes_v20_traits_and_seeded_disciplines(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/character-sheet/catalog')
            ->assertOk()
            ->assertJsonPath('catalog.attributes.physical.0.key', 'strength')
            ->assertJsonPath('catalog.health_boxes.6.label', 'Incapacitated');

        $this->assertTrue(
            Discipline::query()->where('ruleset', 'v20')->where('key', 'potence')->exists(),
        );
    }

    public function test_storyteller_creates_and_lists_character_with_ghouls(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $player = User::factory()->create();
        Sanctum::actingAs($storyteller);

        $pc = $this->postJson('/api/characters', [
            'canonical_name' => 'Анна',
            'character_type' => CharacterType::Player->value,
            'user_id' => $player->id,
            'generation' => 13,
            'nature' => 'Fanatic',
            'demeanor' => 'Survivor',
            'concept' => 'Shaman-alchemist',
        ])->assertCreated()->json('character');

        $this->assertSame('Анна', $pc['canonical_name']);
        $this->assertSame(0, $pc['experience']);
        $this->assertNull($pc['biography']);

        $ghoul = $this->postJson('/api/characters', [
            'canonical_name' => 'Иван',
            'character_type' => CharacterType::Ghoul->value,
            'domitor_character_id' => $pc['id'],
        ])->assertCreated()->json('character');

        $this->assertSame($pc['id'], $ghoul['domitor_character_id']);

        $list = $this->getJson('/api/characters')->assertOk()->json();
        $this->assertCount(1, $list['characters']);
        $this->assertSame('Анна', $list['characters'][0]['canonical_name']);
        $this->assertSame('Иван', $list['characters'][0]['ghouls'][0]['canonical_name']);
        $this->assertSame([], $list['archived']);
    }

    public function test_player_sees_own_sheet_and_cannot_see_npc(): void
    {
        $player = User::factory()->create();
        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->orderBy('id')->firstOrFail();
        $service = $this->app->make(WorldEntityService::class);
        $pc = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $player->id,
            ],
        );
        $npc = $service->create($chronicle, WorldEntityType::Character, 'Виктория');

        Sanctum::actingAs($player);
        $this->getJson('/api/characters/'.$pc->id)->assertOk()->assertJsonPath('character.canonical_name', 'Анна');
        $this->getJson('/api/characters/'.$npc->id)->assertForbidden();

        Sanctum::actingAs($storyteller);
        $this->getJson('/api/characters/'.$npc->id)->assertOk();
    }

    public function test_player_cannot_create_character(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/characters', [
            'canonical_name' => 'Анна',
            'character_type' => CharacterType::Npc->value,
        ])->assertForbidden();
    }

    public function test_stats_status_health_merits_experience_and_disciplines_round_trip(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $character = Character::factory()->create();
        $potence = Discipline::query()->where('key', 'potence')->firstOrFail();
        Sanctum::actingAs($storyteller);

        $this->putJson('/api/characters/'.$character->id.'/stats', [
            'stats' => [
                [
                    'category' => 'attribute',
                    'stat_key' => 'strength',
                    'display_name' => 'Strength',
                    'value' => 3,
                    'maximum' => 5,
                    'sort_order' => 1,
                ],
                [
                    'category' => 'ability',
                    'stat_key' => 'brawl',
                    'display_name' => 'Brawl',
                    'value' => 2,
                    'sort_order' => 13,
                    'specializations' => [['name' => 'Клинки']],
                ],
            ],
        ])->assertOk()
            ->assertJsonPath('character.stats.0.stat_key', 'strength')
            ->assertJsonPath('character.stats.0.value', 3)
            ->assertJsonPath('character.stats.1.specializations.0.name', 'Клинки');

        $this->patchJson('/api/characters/'.$character->id.'/status', [
            'revision' => 0,
            'blood_pool' => 7,
            'temporary_willpower' => 3,
        ])->assertOk();

        $afterStatus = $this->getJson('/api/characters/'.$character->id)->assertOk()->json('character');
        $this->assertSame(7, $afterStatus['status']['blood_pool']);
        $this->assertSame(3, $afterStatus['status']['temporary_willpower']);
        $this->assertSame(1, $afterStatus['status']['revision']);
        $this->assertArrayNotHasKey('hunger', $afterStatus['status']);

        $this->patchJson('/api/characters/'.$character->id.'/status', [
            'revision' => 0,
            'blood_pool' => 5,
        ])->assertConflict();

        $this->putJson('/api/characters/'.$character->id.'/health', [
            'boxes' => [
                ['index' => 0, 'damage' => 'bashing'],
                ['index' => 2, 'damage' => 'lethal'],
            ],
        ])->assertOk()
            ->assertJsonPath('character.health_boxes.0.damage', 'bashing')
            ->assertJsonPath('character.health_boxes.2.damage', 'lethal')
            ->assertJsonPath('character.status.health_state', CharacterHealthState::Injured->value);

        $this->putJson('/api/characters/'.$character->id.'/merits', [
            'merits_flaws' => [
                ['kind' => 'merit', 'name' => 'Medium', 'cost' => 2],
                ['kind' => 'flaw', 'name' => 'Phobia', 'cost' => 2, 'note' => 'Fire'],
            ],
        ])->assertOk()
            ->assertJsonPath('character.merits_flaws.0.name', 'Medium')
            ->assertJsonPath('character.merits_flaws.1.kind', 'flaw');

        $this->patchJson('/api/characters/'.$character->id.'/experience', [
            'experience' => 12,
        ])->assertOk()->assertJsonPath('character.experience', 12);

        $this->putJson('/api/characters/'.$character->id.'/disciplines', [
            'disciplines' => [
                ['discipline_id' => $potence->id, 'level' => 1],
            ],
        ])->assertOk()->assertJsonPath('character.disciplines.0.key', 'potence');
    }

    public function test_player_can_edit_own_ghoul_sheet(): void
    {
        $player = User::factory()->create();
        $chronicle = Chronicle::query()->orderBy('id')->firstOrFail();
        $service = $this->app->make(WorldEntityService::class);
        $pc = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $player->id,
            ],
        );
        $ghoul = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Иван',
            typed: [
                'character_type' => CharacterType::Ghoul,
                'domitor_character_id' => $pc->id,
            ],
        );

        Sanctum::actingAs($player);
        $this->putJson('/api/characters/'.$ghoul->id.'/stats', [
            'stats' => [[
                'category' => 'attribute',
                'stat_key' => 'strength',
                'display_name' => 'Strength',
                'value' => 2,
            ]],
        ])->assertOk()->assertJsonPath('character.stats.0.value', 2);

        Sanctum::actingAs(User::factory()->create());
        $this->putJson('/api/characters/'.$ghoul->id.'/stats', [
            'stats' => [[
                'category' => 'attribute',
                'stat_key' => 'strength',
                'display_name' => 'Strength',
                'value' => 5,
            ]],
        ])->assertForbidden();
    }

    public function test_storyteller_and_owner_can_publish_biography_and_rebuild_index(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $player = User::factory()->create();
        $chronicle = Chronicle::query()->orderBy('id')->firstOrFail();
        $service = $this->app->make(WorldEntityService::class);
        $pc = $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Анна',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $player->id,
            ],
        );
        $npc = $service->create($chronicle, WorldEntityType::Character, 'Виктория');

        Sanctum::actingAs($storyteller);
        $this->putJson('/api/characters/'.$npc->id.'/biography', [
            'summary' => 'Виктория служит Камарилье.',
            'full_text' => 'Она хранит Маскарад.',
            'principles' => 'Не раскрывать природу смертным.',
        ])
            ->assertOk()
            ->assertJsonPath('character.biography.summary', 'Виктория служит Камарилье.')
            ->assertJsonPath('character.biography.full_text', 'Она хранит Маскарад.')
            ->assertJsonPath('character.biography.principles', 'Не раскрывать природу смертным.')
            ->assertJsonPath('character.biography.current_version', 1)
            ->assertJsonPath('character.biography.status', 'approved');

        $this->assertDatabaseHas('character_biography_versions', [
            'character_id' => $npc->id,
            'version' => 1,
            'change_reason' => 'Правка с листа',
        ]);
        $this->assertDatabaseHas('character_bio_chunks', [
            'character_id' => $npc->id,
            'content' => 'Виктория служит Камарилье.',
        ]);

        $this->putJson('/api/characters/'.$npc->id.'/biography', [
            'summary' => 'Виктория служит Камарилье.',
            'full_text' => 'Она хранит Маскарад.',
            'principles' => 'Не раскрывать природу смертным.',
        ])->assertOk()->assertJsonPath('character.biography.current_version', 1);

        Sanctum::actingAs($player);
        $this->putJson('/api/characters/'.$pc->id.'/biography', [
            'summary' => 'Анна ищет Грибобаса.',
        ])->assertOk()->assertJsonPath('character.biography.summary', 'Анна ищет Грибобаса.');

        $this->putJson('/api/characters/'.$npc->id.'/biography', [
            'summary' => 'Чужой канон.',
        ])->assertForbidden();

        $this->putJson('/api/characters/'.$pc->id.'/biography', [
            'principles' => 'Только принципы.',
        ])->assertUnprocessable();
    }

    public function test_storyteller_archives_and_restores_character_with_ghouls(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $player = User::factory()->create();
        Sanctum::actingAs($storyteller);

        $pc = $this->postJson('/api/characters', [
            'canonical_name' => 'Анна',
            'character_type' => CharacterType::Player->value,
            'user_id' => $player->id,
        ])->assertCreated()->json('character');

        $ghoul = $this->postJson('/api/characters', [
            'canonical_name' => 'Иван',
            'character_type' => CharacterType::Ghoul->value,
            'domitor_character_id' => $pc['id'],
        ])->assertCreated()->json('character');

        $scene = Scene::query()->active()->firstOrFail();
        $this->postJson('/api/scenes/'.$scene->id.'/participants', [
            'character_id' => $pc['id'],
            'role' => 'player',
        ])->assertCreated();

        $this->postJson('/api/characters/'.$pc['id'].'/archive')->assertOk()
            ->assertJsonPath('character.is_active', false);

        $this->assertFalse((bool) Character::query()->findOrFail($pc['id'])->is_active);
        $this->assertFalse((bool) Character::query()->findOrFail($ghoul['id'])->is_active);
        $this->assertFalse(
            SceneParticipant::query()
                ->where('character_id', $pc['id'])
                ->where('is_current', true)
                ->exists(),
        );

        $list = $this->getJson('/api/characters')->assertOk()->json();
        $this->assertSame([], $list['characters']);
        $this->assertCount(2, $list['archived']);

        $this->postJson('/api/scenes/'.$scene->id.'/participants', [
            'character_id' => $pc['id'],
            'role' => 'player',
        ])->assertUnprocessable();

        Sanctum::actingAs($player);
        $this->postJson('/api/characters/'.$pc['id'].'/archive')->assertForbidden();
        $this->getJson('/api/characters')->assertOk()->assertJsonPath('archived', []);

        Sanctum::actingAs($storyteller);
        $this->postJson('/api/characters/'.$pc['id'].'/restore')->assertOk()
            ->assertJsonPath('character.is_active', true);

        $restored = $this->getJson('/api/characters')->assertOk()->json();
        $this->assertCount(1, $restored['characters']);
        $this->assertSame('Иван', $restored['characters'][0]['ghouls'][0]['canonical_name']);
        $this->assertSame([], $restored['archived']);
    }
}
