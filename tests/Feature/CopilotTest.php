<?php

namespace Tests\Feature;

use App\Enums\GameSessionStatus;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CopilotTest extends TestCase
{
    private User $storyteller;

    private Chronicle $chronicle;

    private GameSession $gameSession;

    private int $sceneId;

    private int $npcId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->storyteller()->create();
        $this->chronicle = Chronicle::query()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->gameSession = GameSession::query()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Тестовая сессия',
            'status' => GameSessionStatus::Active,
            'created_by' => $this->storyteller->id,
        ]);

        $created = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Сцена',
            ]);
        $created->assertStatus(201);
        $this->sceneId = (int) $created->json('scene.id');

        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
                'chronicle_id' => $this->chronicle->id,
            ]);
        $npc->assertStatus(201);
        $this->npcId = (int) $npc->json('character.id');

        config(['llm.deepseek.api_key' => 'test-key']);
    }

    public function test_copilot_returns_drafts(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::sequence()
                ->push(['choices' => [['message' => ['content' => '{"topics":["test topic"]}']]]], 200)
                ->push(['choices' => [['message' => ['content' => '{"drafts":["one","two","three"]}']]]], 200),
        ]);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/copilot/drafts', [
                'character_id' => $this->npcId,
                'prompt' => 'Мрачно смотрит',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(200);
        $response->assertJsonCount(3, 'drafts');
        $response->assertJsonPath('drafts.0', 'one');
        $response->assertJsonStructure(['copilot_request_id']);
        $this->assertDatabaseHas('copilot_requests', [
            'scene_id' => $this->sceneId,
            'storyteller_id' => $this->storyteller->id,
            'character_id' => $this->npcId,
        ]);
    }

    public function test_copilot_requires_storyteller(): void
    {
        $player = User::factory()->create();

        $response = $this->actingAs($player, 'sanctum')
            ->postJson('/api/copilot/drafts', [
                'character_id' => $this->npcId,
                'prompt' => 'Мрачно смотрит',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('copilot_requests', 0);
    }

    public function test_copilot_requires_active_scene(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'should not be called'], 500),
        ]);

        $second = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Черновик',
                'activate' => false,
            ]);
        $second->assertStatus(201);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/copilot/drafts', [
                'character_id' => $this->npcId,
                'prompt' => 'Мрачно смотрит',
                'scene_id' => $second->json('scene.id'),
            ]);

        $response->assertStatus(409);
    }

    public function test_copilot_returns_503_when_llm_unavailable(): void
    {
        Http::fake([
            'api.deepseek.com/*' => Http::response(['error' => 'server down'], 500),
        ]);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/copilot/drafts', [
                'character_id' => $this->npcId,
                'prompt' => 'Мрачно смотрит',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(503);
        $this->assertDatabaseCount('copilot_requests', 0);
    }
}
