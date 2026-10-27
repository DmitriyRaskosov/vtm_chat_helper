<?php

namespace Tests\Feature;

use App\Chronicle\CurrentChronicle;
use App\Chronicle\Exceptions\NoChronicleException;
use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CurrentChronicleTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
    }

    public function test_current_chronicle_without_route_parameter_returns_409(): void
    {
        try {
            $this->app->make(CurrentChronicle::class)->get();
            $this->fail('Expected NoChronicleException.');
        } catch (NoChronicleException $e) {
            $this->assertSame('There is no available chronicle.', $e->getMessage());
        }
    }

    public function test_active_game_session_without_active_session_returns_null(): void
    {
        $chronicle = Chronicle::factory()->create([
            'title' => 'Пустая',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $chronicle, ChronicleRole::Storyteller);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($chronicle, 'game-sessions/active'));

        $response->assertStatus(200);
        $response->assertJsonPath('game_session', null);
    }

    public function test_current_chronicle_comes_from_url_not_minimum_id(): void
    {
        $first = Chronicle::factory()->create([
            'title' => 'Первая',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $first, ChronicleRole::Storyteller);
        $second = Chronicle::factory()->create([
            'title' => 'Вторая',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $second, ChronicleRole::Storyteller);

        GameSession::factory()->active()->create([
            'chronicle_id' => $first->id,
            'title' => 'Сессия первой',
            'created_by' => $this->storyteller->id,
        ]);
        $secondSession = GameSession::factory()->active()->create([
            'chronicle_id' => $second->id,
            'title' => 'Сессия второй',
            'created_by' => $this->storyteller->id,
        ]);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($second, 'game-sessions/active'));

        $response->assertStatus(200);
        $response->assertJsonPath('game_session.id', $secondSession->id);
        $response->assertJsonPath('game_session.chronicle_id', $second->id);
    }

    public function test_chronicle_id_query_on_characters_list_returns_422(): void
    {
        $chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $chronicle, ChronicleRole::Storyteller);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($chronicle, 'characters').'?chronicle_id=1');

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['chronicle_id']);
    }

    public function test_chronicle_id_in_create_character_body_returns_422(): void
    {
        $chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $chronicle, ChronicleRole::Storyteller);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($chronicle, 'characters'), [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
                'chronicle_id' => $chronicle->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['chronicle_id']);
    }

    public function test_chronicle_id_in_message_body_returns_422(): void
    {
        $chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $chronicle, ChronicleRole::Storyteller);
        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $chronicle->id,
            'title' => 'Сессия',
            'created_by' => $this->storyteller->id,
        ]);
        $scene = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$gameSession->id.'/scenes', [
                'title' => 'Сцена',
            ]);
        $scene->assertStatus(201);
        $sceneId = (int) $scene->json('scene.id');

        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($chronicle, 'characters'), [
                'canonical_name' => 'NPC',
                'character_type' => 'npc',
            ]);
        $npc->assertStatus(201);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($chronicle, 'messages'), [
                'body' => 'Привет',
                'scene_id' => $sceneId,
                'character_id' => $npc->json('character.id'),
                'chronicle_id' => $chronicle->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['chronicle_id']);
    }

    public function test_chronicle_id_in_copilot_drafts_body_returns_422(): void
    {
        $chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $chronicle, ChronicleRole::Storyteller);
        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $chronicle->id,
            'title' => 'Сессия',
            'created_by' => $this->storyteller->id,
        ]);
        $scene = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$gameSession->id.'/scenes', [
                'title' => 'Сцена',
            ]);
        $scene->assertStatus(201);
        $sceneId = (int) $scene->json('scene.id');

        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($chronicle, 'characters'), [
                'canonical_name' => 'NPC',
                'character_type' => 'npc',
            ]);
        $npc->assertStatus(201);
        $npcId = (int) $npc->json('character.id');

        $this->fakeChat()
            ->push('{"topics":["test topic"]}')
            ->push('{"drafts":["one","two","three"]}');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($chronicle, 'copilot/drafts'), [
                'character_id' => $npcId,
                'prompt' => 'Синопсис',
                'scene_id' => $sceneId,
                'chronicle_id' => $chronicle->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['chronicle_id']);
    }
}
