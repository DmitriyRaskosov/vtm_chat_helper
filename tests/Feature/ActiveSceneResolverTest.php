<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Enums\GameSessionStatus;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class ActiveSceneResolverTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

    private GameSession $gameSession;

    private int $activeSceneId;

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
                'title' => 'Активная',
            ]);
        $created->assertStatus(201);
        $this->activeSceneId = (int) $created->json('scene.id');
    }

    public function test_index_accepts_inactive_scene_by_id(): void
    {
        $draft = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Черновик',
                'activate' => false,
            ]);
        $draft->assertStatus(201);
        $draftSceneId = (int) $draft->json('scene.id');

        Message::factory()->create([
            'scene_id' => $draftSceneId,
            'user_id' => $this->storyteller->id,
            'body' => 'История черновика',
        ]);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$draftSceneId);

        $response->assertStatus(200);
        $response->assertJsonCount(1, 'messages');
        $response->assertJsonPath('messages.0.body', 'История черновика');
    }

    public function test_store_on_inactive_scene_returns_409_with_messages_text(): void
    {
        $draft = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Черновик',
                'activate' => false,
            ]);
        $draftSceneId = (int) $draft->json('scene.id');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'X',
                'scene_id' => $draftSceneId,
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Messages can only be posted to the active scene.');
    }

    public function test_store_without_active_scene_returns_409_with_no_scene_text(): void
    {
        $this->gameSession->update(['status' => GameSessionStatus::Archived]);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'X',
                'scene_id' => $this->activeSceneId,
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'There is no available scene in the active game session.');
    }

    public function test_copilot_on_inactive_scene_returns_409_with_copilot_text(): void
    {
        $this->fakeChat();

        $npcId = $this->createNpc();

        $draft = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Черновик',
                'activate' => false,
            ]);
        $draftSceneId = (int) $draft->json('scene.id');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'copilot/drafts'), [
                'character_id' => $npcId,
                'prompt' => 'Тест',
                'scene_id' => $draftSceneId,
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Copilot requires an active scene.');
    }

    public function test_copilot_with_character_from_other_chronicle_returns_409(): void
    {
        $this->fakeChat();

        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $otherChronicle, ChronicleRole::Storyteller);

        $foreign = Character::factory()
            ->forChronicle($otherChronicle)
            ->create(['canonical_name' => 'Чужой NPC']);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'copilot/drafts'), [
                'character_id' => $foreign->id,
                'prompt' => 'Тест',
                'scene_id' => $this->activeSceneId,
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Character does not belong to this chronicle.');
    }

    public function test_get_messages_with_own_scene_id_returns_200(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$this->activeSceneId);

        $response->assertStatus(200);
        $response->assertJsonStructure(['messages']);
    }

    public function test_get_messages_with_foreign_chronicle_scene_id_returns_409_for_storyteller(): void
    {
        [, $foreignSceneId] = $this->createForeignChronicleActiveScene();

        Message::factory()->create([
            'scene_id' => $foreignSceneId,
            'user_id' => $this->storyteller->id,
            'body' => 'Секрет другой хроники',
        ]);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$foreignSceneId);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'There is no available scene in the active game session.');
    }

    public function test_get_messages_with_foreign_chronicle_scene_id_returns_409_for_player(): void
    {
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        [, $foreignSceneId] = $this->createForeignChronicleActiveScene();

        Message::factory()->create([
            'scene_id' => $foreignSceneId,
            'user_id' => $this->storyteller->id,
            'body' => 'Секрет другой хроники',
        ]);

        $response = $this->actingAs($player, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$foreignSceneId);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'There is no available scene in the active game session.');
    }

    public function test_message_with_character_from_other_chronicle_returns_409(): void
    {
        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $otherChronicle, ChronicleRole::Storyteller);

        $foreign = Character::factory()
            ->forChronicle($otherChronicle)
            ->create(['canonical_name' => 'Чужой NPC']);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'X',
                'scene_id' => $this->activeSceneId,
                'character_id' => $foreign->id,
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Character does not belong to this chronicle.');
    }

    /**
     * @return array{0: Chronicle, 1: int}
     */
    private function createForeignChronicleActiveScene(): array
    {
        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $otherChronicle, ChronicleRole::Storyteller);

        $otherSession = GameSession::factory()->active()->create([
            'chronicle_id' => $otherChronicle->id,
            'title' => 'Сессия другой хроники',
            'created_by' => $this->storyteller->id,
        ]);

        $created = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$otherSession->id.'/scenes', [
                'title' => 'Чужая сцена',
            ]);
        $created->assertStatus(201);

        return [$otherChronicle, (int) $created->json('scene.id')];
    }

    private function createNpc(): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'NPC',
                'character_type' => 'npc',
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }
}
