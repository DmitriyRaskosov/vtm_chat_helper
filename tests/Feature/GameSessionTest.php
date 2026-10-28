<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\SceneContext;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class GameSessionTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
    }

    public function test_active_without_session_returns_null(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'game-sessions/active'));

        $response->assertStatus(200);
        $response->assertJsonPath('game_session', null);
    }

    public function test_store_archives_previous_active_session_and_closes_scenes(): void
    {
        $previous = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Старая сессия',
            'created_by' => $this->storyteller->id,
        ]);
        $openScene = Scene::factory()->create([
            'game_session_id' => $previous->id,
            'status' => SceneStatus::Active,
            'position' => 1,
        ]);

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/scenes/{$openScene->id}/context", [
                'expected_revision' => 0,
                'situation' => 'До архивации',
            ])
            ->assertStatus(200);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'game-sessions'), [
                'title' => 'Новая сессия',
            ]);

        $response->assertStatus(201);
        $newSessionId = (int) $response->json('game_session.id');

        $this->assertDatabaseHas('game_sessions', [
            'id' => $previous->id,
            'status' => GameSessionStatus::Archived->value,
        ]);
        $this->assertDatabaseHas('scenes', [
            'id' => $openScene->id,
            'status' => SceneStatus::Closed->value,
        ]);
        $this->assertDatabaseHas('scene_contexts', [
            'scene_id' => $openScene->id,
        ]);
        $this->assertNotNull(
            SceneContext::query()->where('scene_id', $openScene->id)->value('frozen_revision'),
        );

        $response->assertJsonPath('game_session.status', GameSessionStatus::Active->value);
        $response->assertJsonPath('game_session.scenes.0.title', 'Начальная сцена');
        $response->assertJsonPath('game_session.scenes.0.status', SceneStatus::Active->value);

        $this->assertDatabaseHas('game_sessions', [
            'id' => $newSessionId,
            'status' => GameSessionStatus::Active->value,
            'title' => 'Новая сессия',
        ]);
    }

    public function test_storyteller_can_set_and_clear_active_turn_character(): void
    {
        $session = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Сессия',
            'created_by' => $this->storyteller->id,
        ]);

        $playerPc = Character::factory()->player()->create([
            'chronicle_id' => $this->chronicle->id,
            'canonical_name' => 'Игроков Перс',
        ]);

        $set = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson('/api/game-sessions/'.$session->id.'/turn', [
                'character_id' => $playerPc->id,
            ]);
        $set->assertOk();
        $set->assertJsonPath('game_session.active_character_id', $playerPc->id);

        $clear = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson('/api/game-sessions/'.$session->id.'/turn', [
                'character_id' => null,
            ]);
        $clear->assertOk();
        $clear->assertJsonPath('game_session.active_character_id', null);
    }

    public function test_turn_cannot_be_set_to_npc(): void
    {
        $session = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);

        $npc = Character::factory()->npc()->create([
            'chronicle_id' => $this->chronicle->id,
            'canonical_name' => 'НПС',
        ]);

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson('/api/game-sessions/'.$session->id.'/turn', [
                'character_id' => $npc->id,
            ])
            ->assertStatus(422);
    }

    public function test_player_can_release_turn_when_it_is_theirs(): void
    {
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $session = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);

        $playerPc = Character::factory()->player($player)->create([
            'chronicle_id' => $this->chronicle->id,
            'canonical_name' => 'Мой герой',
        ]);

        $session->update(['active_character_id' => $playerPc->id]);

        $this->actingAs($player, 'sanctum')
            ->patchJson('/api/game-sessions/'.$session->id.'/turn', [
                'character_id' => null,
            ])
            ->assertOk()
            ->assertJsonPath('game_session.active_character_id', null);
    }

    public function test_player_cannot_release_turn_when_it_is_not_theirs(): void
    {
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $session = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);

        $otherPc = Character::factory()->player()->create([
            'chronicle_id' => $this->chronicle->id,
        ]);
        $session->update(['active_character_id' => $otherPc->id]);

        $this->actingAs($player, 'sanctum')
            ->patchJson('/api/game-sessions/'.$session->id.'/turn', [
                'character_id' => null,
            ])
            ->assertForbidden();
    }

    public function test_player_cannot_assign_turn_to_character(): void
    {
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $session = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);

        $playerPc = Character::factory()->player($player)->create([
            'chronicle_id' => $this->chronicle->id,
        ]);

        $this->actingAs($player, 'sanctum')
            ->patchJson('/api/game-sessions/'.$session->id.'/turn', [
                'character_id' => $playerPc->id,
            ])
            ->assertForbidden();
    }

    public function test_cannot_add_scene_to_archived_session(): void
    {
        $archived = GameSession::factory()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Архивная сессия',
            'status' => GameSessionStatus::Archived,
            'created_by' => $this->storyteller->id,
        ]);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$archived->id.'/scenes', [
                'title' => 'Лишняя сцена',
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Scenes can only be added to the active game session.');
    }
}
