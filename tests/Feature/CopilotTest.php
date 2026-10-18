<?php

namespace Tests\Feature;

use App\Context\ContextBuilder;
use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\CopilotRequest;
use App\Models\GameSession;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CopilotTest extends TestCase
{
    use JoinsChronicle;

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
        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Тестовая сессия',
            'created_by' => $this->storyteller->id,
        ]);

        $created = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Сцена',
            ]);
        $created->assertStatus(201);
        $this->sceneId = (int) $created->json('scene.id');

        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
            ]);
        $npc->assertStatus(201);
        $this->npcId = (int) $npc->json('character.id');
    }

    public function test_copilot_returns_drafts(): void
    {
        $this->fakeChat()
            ->push('{"topics":["test topic"]}')
            ->push('{"drafts":["one","two","three"]}');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'copilot/drafts'), [
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
            ->postJson($this->chronicleApi($this->chronicle, 'copilot/drafts'), [
                'character_id' => $this->npcId,
                'prompt' => 'Мрачно смотрит',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(403);
        $this->assertDatabaseCount('copilot_requests', 0);
    }

    public function test_copilot_requires_active_scene(): void
    {
        $this->fakeChat();

        $second = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Черновик',
                'activate' => false,
            ]);
        $second->assertStatus(201);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'copilot/drafts'), [
                'character_id' => $this->npcId,
                'prompt' => 'Мрачно смотрит',
                'scene_id' => $second->json('scene.id'),
            ]);

        $response->assertStatus(409);
    }

    public function test_copilot_returns_503_when_llm_unavailable(): void
    {
        $this->fakeChat();

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'copilot/drafts'), [
                'character_id' => $this->npcId,
                'prompt' => 'Мрачно смотрит',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(503);
        $response->assertJsonPath('message', 'LLM provider is unavailable.');
        $this->assertDatabaseCount('copilot_requests', 0);
    }

    public function test_copilot_returns_502_when_draft_response_is_invalid(): void
    {
        $this->fakeChat()
            ->push('{"topics":["test topic"]}')
            ->push('   ');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'copilot/drafts'), [
                'character_id' => $this->npcId,
                'prompt' => 'Мрачно смотрит',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(502);
        $response->assertJsonPath(
            'message',
            'Could not parse draft replies from the model response.',
        );
        $this->assertDatabaseCount('copilot_requests', 0);
    }

    public function test_copilot_persists_copilot_request_with_metadata(): void
    {
        $this->fakeChat()
            ->push('{"topics":["test topic"]}')
            ->push('{"drafts":["one","two","three"]}');

        $prompt = 'Мрачно смотрит';

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'copilot/drafts'), [
                'character_id' => $this->npcId,
                'prompt' => $prompt,
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(200);

        $copilotRequestId = (int) $response->json('copilot_request_id');
        $row = CopilotRequest::query()->findOrFail($copilotRequestId);

        $this->assertSame($this->sceneId, (int) $row->scene_id);
        $this->assertSame($this->storyteller->id, (int) $row->storyteller_id);
        $this->assertSame($this->npcId, (int) $row->character_id);
        $this->assertSame('Иван', $row->npc_name);
        $this->assertSame($prompt, $row->prompt);
        $this->assertSame(['one', 'two', 'three'], $row->drafts);
        $this->assertIsArray($row->context_metadata);
        $this->assertNotEmpty($row->context_metadata);
        $this->assertSame(ContextBuilder::PROMPT_VERSION, $row->prompt_version);
        $this->assertSame(ContextBuilder::VERSION, $row->builder_version);
        $this->assertNotSame('', (string) $row->model);
    }
}
