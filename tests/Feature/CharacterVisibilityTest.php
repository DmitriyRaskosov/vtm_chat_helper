<?php

namespace Tests\Feature;

use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\TestCase;

class CharacterVisibilityTest extends TestCase
{
    private User $storyteller;

    private Chronicle $chronicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->storyteller()->create();
        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
    }

    public function test_storyteller_sees_active_and_archived_characters(): void
    {
        $activeId = $this->createCharacter('Активный');
        $archivedId = $this->createCharacter('Архивный');

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/characters/{$archivedId}/archive")
            ->assertStatus(200);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson('/api/characters?chronicle_id='.$this->chronicle->id);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'characters');
        $response->assertJsonPath('characters.0.id', $activeId);
        $response->assertJsonCount(1, 'archived');
        $response->assertJsonPath('archived.0.id', $archivedId);
    }

    public function test_player_sees_own_character_and_current_active_scene_participants(): void
    {
        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Сессия',
            'created_by' => $this->storyteller->id,
        ]);

        $activeScene = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$gameSession->id.'/scenes', [
                'title' => 'Активная сцена',
            ]);
        $activeScene->assertStatus(201);
        $activeSceneId = (int) $activeScene->json('scene.id');

        $draftScene = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$gameSession->id.'/scenes', [
                'title' => 'Черновик',
                'activate' => false,
            ]);
        $draftScene->assertStatus(201);
        $draftSceneId = (int) $draftScene->json('scene.id');

        $player = User::factory()->create();
        $pcId = $this->createPlayerCharacter($player, 'Мой герой');

        $visibleNpcId = $this->createCharacter('В сцене');
        $leftNpcId = $this->createCharacter('Ушёл');
        $draftSceneNpcId = $this->createCharacter('В черновике');
        $unrelatedNpcId = $this->createCharacter('Вне сцены');
        $archivedNpcId = $this->createCharacter('Архив в сцене');

        foreach ([
            [$activeSceneId, $pcId, 'player'],
            [$activeSceneId, $visibleNpcId, 'npc'],
            [$activeSceneId, $leftNpcId, 'npc'],
            [$draftSceneId, $draftSceneNpcId, 'npc'],
        ] as [$sceneId, $characterId, $role]) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson("/api/scenes/{$sceneId}/participants", [
                    'character_id' => $characterId,
                    'role' => $role,
                ])
                ->assertStatus(201);
        }

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$activeSceneId}/participants/{$leftNpcId}")
            ->assertStatus(200);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/characters/{$archivedNpcId}/archive")
            ->assertStatus(200);

        $response = $this->actingAs($player, 'sanctum')
            ->getJson('/api/characters?chronicle_id='.$this->chronicle->id);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'characters');
        $response->assertJsonPath('archived', []);
        $ids = collect($response->json('characters'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$pcId, $visibleNpcId], $ids);
        $this->assertNotContains($leftNpcId, $ids);
        $this->assertNotContains($draftSceneNpcId, $ids);
        $this->assertNotContains($unrelatedNpcId, $ids);
        $this->assertNotContains($archivedNpcId, $ids);
    }

    public function test_player_without_scene_sees_only_own_character(): void
    {
        $player = User::factory()->create();
        $pcId = $this->createPlayerCharacter($player, 'Одинокий герой');
        $this->createCharacter('Чужой NPC');

        $response = $this->actingAs($player, 'sanctum')
            ->getJson('/api/characters?chronicle_id='.$this->chronicle->id);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'characters');
        $response->assertJsonPath('characters.0.id', $pcId);
        $response->assertJsonPath('archived', []);
    }

    public function test_player_without_character_gets_empty_list(): void
    {
        $this->createCharacter('NPC');

        $player = User::factory()->create();

        $response = $this->actingAs($player, 'sanctum')
            ->getJson('/api/characters?chronicle_id='.$this->chronicle->id);

        $response->assertStatus(200);
        $response->assertJsonPath('characters', []);
        $response->assertJsonPath('archived', []);
    }

    private function createCharacter(string $name): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => $name,
                'character_type' => 'npc',
                'chronicle_id' => $this->chronicle->id,
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }

    private function createPlayerCharacter(User $player, string $name): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => $name,
                'character_type' => 'player',
                'chronicle_id' => $this->chronicle->id,
                'user_id' => $player->id,
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }
}
