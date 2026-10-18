<?php

namespace Tests\Feature;

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

        $this->storyteller = User::factory()->storyteller()->create();
    }

    public function test_active_game_session_without_chronicles_returns_409(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson('/api/game-sessions/active');

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'There is no available chronicle.');
    }

    public function test_current_chronicle_is_first_by_id(): void
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

        $firstSession = GameSession::factory()->active()->create([
            'chronicle_id' => $first->id,
            'title' => 'Сессия первой',
            'created_by' => $this->storyteller->id,
        ]);
        GameSession::factory()->active()->create([
            'chronicle_id' => $second->id,
            'title' => 'Сессия второй',
            'created_by' => $this->storyteller->id,
        ]);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson('/api/game-sessions/active');

        $response->assertStatus(200);
        $response->assertJsonPath('game_session.id', $firstSession->id);
        $response->assertJsonPath('game_session.chronicle_id', $first->id);
    }

    public function test_chronicle_id_query_on_characters_list_returns_422(): void
    {
        Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson('/api/characters?chronicle_id=1');

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
            ->postJson('/api/characters', [
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
            ->postJson('/api/characters', [
                'canonical_name' => 'NPC',
                'character_type' => 'npc',
            ]);
        $npc->assertStatus(201);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/messages', [
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
            ->postJson('/api/characters', [
                'canonical_name' => 'NPC',
                'character_type' => 'npc',
            ]);
        $npc->assertStatus(201);
        $npcId = (int) $npc->json('character.id');

        $this->fakeChat()
            ->push('{"topics":["test topic"]}')
            ->push('{"drafts":["one","two","three"]}');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/copilot/drafts', [
                'character_id' => $npcId,
                'prompt' => 'Синопсис',
                'scene_id' => $sceneId,
                'chronicle_id' => $chronicle->id,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['chronicle_id']);
    }
}
