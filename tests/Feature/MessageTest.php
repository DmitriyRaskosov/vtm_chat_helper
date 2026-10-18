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

class MessageTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

    private GameSession $gameSession;

    private int $sceneId;

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
    }

    public function test_user_can_send_message(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'Привет от мастера',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.body', 'Привет от мастера');
        $this->assertDatabaseHas('messages', [
            'scene_id' => $this->sceneId,
            'body' => 'Привет от мастера',
            'user_id' => $this->storyteller->id,
        ]);
    }

    public function test_message_requires_active_scene(): void
    {
        $second = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Черновик',
                'activate' => false,
            ]);
        $second->assertStatus(201);
        $secondSceneId = $second->json('scene.id');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'X',
                'scene_id' => $secondSceneId,
            ]);

        $response->assertStatus(409);
        $this->assertDatabaseMissing('messages', [
            'body' => 'X',
        ]);
    }

    public function test_messages_returns_scene_history(): void
    {
        foreach (['Первое', 'Второе'] as $body) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                    'body' => $body,
                    'scene_id' => $this->sceneId,
                ])
                ->assertStatus(201);
        }

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$this->sceneId);

        $response->assertStatus(200);
        $response->assertJsonCount(2, 'messages');
        $response->assertJsonPath('messages.0.body', 'Первое');
        $response->assertJsonPath('messages.1.body', 'Второе');
    }

    public function test_post_message_with_copilot_draft_sets_selected_draft_index(): void
    {
        $npcId = $this->createNpcInScene();
        $copilotRequestId = $this->createCopilotRequest($npcId);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'scene_id' => $this->sceneId,
                'character_id' => $npcId,
                'body' => 'b',
                'copilot_request_id' => $copilotRequestId,
                'copilot_draft_index' => 1,
            ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('copilot_requests', [
            'id' => $copilotRequestId,
            'selected_draft_index' => 1,
        ]);
    }

    public function test_copilot_request_already_used_returns_409(): void
    {
        $npcId = $this->createNpcInScene();
        $copilotRequestId = $this->createCopilotRequest($npcId);

        $payload = [
            'scene_id' => $this->sceneId,
            'character_id' => $npcId,
            'body' => 'b',
            'copilot_request_id' => $copilotRequestId,
            'copilot_draft_index' => 1,
        ];

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), $payload)
            ->assertStatus(201);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), $payload)
            ->assertStatus(409)
            ->assertJsonPath('message', 'Copilot request was already used.');
    }

    public function test_copilot_request_wrong_storyteller_returns_403(): void
    {
        $npcId = $this->createNpcInScene();
        $copilotRequestId = $this->createCopilotRequest($npcId);
        $other = User::factory()->create();
        $this->joinChronicle($other, $this->chronicle, ChronicleRole::Storyteller);

        $this->actingAs($other, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'scene_id' => $this->sceneId,
                'character_id' => $npcId,
                'body' => 'b',
                'copilot_request_id' => $copilotRequestId,
                'copilot_draft_index' => 0,
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Copilot request belongs to another storyteller.');
    }

    public function test_copilot_request_character_mismatch_returns_409(): void
    {
        $npcId = $this->createNpcInScene();
        $otherNpc = $this->createNpcInScene();
        $copilotRequestId = $this->createCopilotRequest($npcId);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'scene_id' => $this->sceneId,
                'character_id' => $otherNpc,
                'body' => 'b',
                'copilot_request_id' => $copilotRequestId,
                'copilot_draft_index' => 0,
            ])
            ->assertStatus(409)
            ->assertJsonPath('message', 'Copilot request does not match this message.');
    }

    public function test_copilot_invalid_draft_index_returns_422(): void
    {
        $npcId = $this->createNpcInScene();
        $copilotRequestId = $this->createCopilotRequest($npcId);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'scene_id' => $this->sceneId,
                'character_id' => $npcId,
                'body' => 'b',
                'copilot_request_id' => $copilotRequestId,
                'copilot_draft_index' => 99,
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', 'Selected Copilot draft does not exist.');

        $this->assertDatabaseMissing('messages', ['copilot_request_id' => $copilotRequestId]);
    }

    private function createNpcInScene(): int
    {
        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'NPC '.uniqid(),
                'character_type' => 'npc',
            ]);
        $npc->assertStatus(201);
        $npcId = (int) $npc->json('character.id');

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/scenes/'.$this->sceneId.'/participants', [
                'character_id' => $npcId,
                'role' => 'npc',
            ])
            ->assertStatus(201);

        return $npcId;
    }

    private function createCopilotRequest(int $npcId): int
    {
        $this->fakeChat()
            ->push('{"topics":["test"]}')
            ->push('{"drafts":["a","b","c"]}');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'copilot/drafts'), [
                'character_id' => $npcId,
                'prompt' => 'Смотрит мрачно',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(200);

        return (int) $response->json('copilot_request_id');
    }
}
