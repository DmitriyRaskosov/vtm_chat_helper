<?php

namespace Tests\Feature;

use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GameSessionSceneTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_read_active_session_and_scenes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/game-sessions/active')
            ->assertOk()
            ->assertJsonPath('game_session.status', 'active')
            ->assertJsonPath('game_session.scenes.0.status', 'active')
            ->assertJsonPath(
                'game_session.active_scene_id',
                Scene::query()->active()->value('id'),
            );
    }

    public function test_storyteller_can_create_a_new_active_session(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $oldSession = GameSession::query()->active()->firstOrFail();

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/game-sessions', ['title' => 'Ночь откровений'])
            ->assertCreated()
            ->assertJsonPath('game_session.title', 'Ночь откровений')
            ->assertJsonPath('game_session.scenes.0.status', 'active');

        $this->assertSame(
            GameSessionStatus::Archived,
            $oldSession->refresh()->status,
        );
        $this->assertDatabaseHas('game_sessions', [
            'title' => 'Ночь откровений',
            'created_by' => $storyteller->id,
            'status' => GameSessionStatus::Active->value,
        ]);
    }

    public function test_storyteller_can_create_switch_and_close_scenes(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $session = GameSession::query()->active()->with('scenes')->firstOrFail();
        $firstScene = $session->scenes->firstOrFail();

        Sanctum::actingAs($storyteller);

        $secondSceneId = $this->postJson("/api/game-sessions/{$session->id}/scenes", [
            'title' => 'Бал в Элизиуме',
            'activate' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('scene.status', 'active')
            ->json('scene.id');

        $this->assertSame(SceneStatus::Draft, $firstScene->refresh()->status);

        $this->patchJson("/api/scenes/{$firstScene->id}/activate")
            ->assertOk()
            ->assertJsonPath('scene.status', 'active');

        $this->assertSame(
            SceneStatus::Draft,
            Scene::query()->findOrFail($secondSceneId)->status,
        );

        $this->patchJson("/api/scenes/{$firstScene->id}/close")
            ->assertOk()
            ->assertJsonPath('scene.status', 'closed');
    }

    public function test_player_cannot_manage_sessions_or_scenes(): void
    {
        $player = User::factory()->create();
        $session = GameSession::query()->active()->firstOrFail();
        $scene = $session->scenes()->firstOrFail();

        Sanctum::actingAs($player);

        $this->postJson('/api/game-sessions', ['title' => 'Запрещено'])->assertForbidden();
        $this->postJson("/api/game-sessions/{$session->id}/scenes", [
            'title' => 'Запрещено',
        ])->assertForbidden();
        $this->patchJson("/api/scenes/{$scene->id}/close")->assertForbidden();
    }
}
