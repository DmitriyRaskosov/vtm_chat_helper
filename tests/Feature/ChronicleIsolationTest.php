<?php

namespace Tests\Feature;

use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChronicleIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_chronicles_keep_independent_active_sessions_scenes_and_messages(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $player = User::factory()->create();

        $chronicleA = Chronicle::query()->orderBy('id')->firstOrFail();
        $sessionA = GameSession::query()
            ->where('chronicle_id', $chronicleA->id)
            ->active()
            ->firstOrFail();
        $sceneA = $sessionA->scenes()->active()->firstOrFail();

        $chronicleB = Chronicle::factory()->create(['title' => 'Вторая хроника']);
        $sessionB = GameSession::factory()->active()->create([
            'chronicle_id' => $chronicleB->id,
            'title' => 'Встреча второй хроники',
        ]);
        $sceneB = Scene::factory()->active()->create([
            'game_session_id' => $sessionB->id,
            'title' => 'Сцена второй хроники',
        ]);

        Sanctum::actingAs($player);

        $this->getJson('/api/game-sessions/active')
            ->assertOk()
            ->assertJsonPath('game_session.id', $sessionA->id)
            ->assertJsonPath('game_session.chronicle_id', $chronicleA->id);

        $this->getJson('/api/game-sessions/active?chronicle_id='.$chronicleB->id)
            ->assertOk()
            ->assertJsonPath('game_session.id', $sessionB->id)
            ->assertJsonPath('game_session.chronicle_id', $chronicleB->id);

        $this->postJson('/api/messages', [
            'body' => 'Реплика хроники A.',
            'scene_id' => $sceneA->id,
            'chronicle_id' => $chronicleA->id,
        ])->assertCreated();

        $this->postJson('/api/messages', [
            'body' => 'Реплика хроники B.',
            'scene_id' => $sceneB->id,
            'chronicle_id' => $chronicleB->id,
        ])->assertCreated();

        $this->getJson('/api/messages?scene_id='.$sceneA->id.'&chronicle_id='.$chronicleA->id)
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Реплика хроники A.');

        $this->getJson('/api/messages?scene_id='.$sceneB->id.'&chronicle_id='.$chronicleB->id)
            ->assertOk()
            ->assertJsonCount(1, 'messages')
            ->assertJsonPath('messages.0.body', 'Реплика хроники B.');

        $this->getJson('/api/messages?scene_id='.$sceneB->id.'&chronicle_id='.$chronicleA->id)
            ->assertStatus(409);

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/game-sessions', [
            'title' => 'Новая встреча A',
            'chronicle_id' => $chronicleA->id,
        ])
            ->assertCreated()
            ->assertJsonPath('game_session.title', 'Новая встреча A')
            ->assertJsonPath('game_session.chronicle_id', $chronicleA->id);

        $this->assertSame(GameSessionStatus::Archived, $sessionA->refresh()->status);
        $this->assertSame(SceneStatus::Closed, $sceneA->refresh()->status);
        $this->assertSame(GameSessionStatus::Active, $sessionB->refresh()->status);
        $this->assertSame(SceneStatus::Active, $sceneB->refresh()->status);

        $this->getJson('/api/game-sessions/active?chronicle_id='.$chronicleB->id)
            ->assertOk()
            ->assertJsonPath('game_session.id', $sessionB->id);
    }

    public function test_a_chronicle_cannot_have_two_active_game_sessions(): void
    {
        $chronicle = Chronicle::factory()->create();
        GameSession::factory()->active()->create(['chronicle_id' => $chronicle->id]);

        $this->expectException(UniqueConstraintViolationException::class);

        GameSession::factory()->active()->create(['chronicle_id' => $chronicle->id]);
    }
}
