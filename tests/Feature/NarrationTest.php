<?php

namespace Tests\Feature;

use App\Context\ContextAssembler;
use App\Context\ContextPass;
use App\Context\ContextRequest;
use App\Diary\DiaryWriterService;
use App\Enums\ChronicleRole;
use App\Enums\SceneParticipantRole;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\CreatesDiaryEntries;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class NarrationTest extends TestCase
{
    use CreatesDiaryEntries;
    use JoinsChronicle;

    private User $storyteller;

    private User $player;

    private Chronicle $chronicle;

    private Scene $scene;

    private Character $npc;

    private int $sceneId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->player = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->player, $this->chronicle, ChronicleRole::Player);

        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Тестовая сессия',
            'created_by' => $this->storyteller->id,
        ]);

        $created = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$gameSession->id.'/scenes', [
                'title' => 'Сцена',
            ]);
        $created->assertStatus(201);
        $this->sceneId = (int) $created->json('scene.id');
        $this->scene = Scene::query()->findOrFail($this->sceneId);
    }

    public function test_storyteller_can_send_narration(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'Абрахам бьёт Порижану.',
                'scene_id' => $this->sceneId,
                'is_narration' => true,
            ]);

        $response->assertStatus(201);
        $response->assertJsonPath('message.is_narration', true);
        $response->assertJsonPath('message.is_ooc', false);
        $response->assertJsonPath('message.author', 'Повествование');
        $response->assertJsonPath('message.author_character_id', null);
        $response->assertJsonPath('message.npc_name', null);
        $response->assertJsonPath('message.avatar_path', null);

        $this->assertDatabaseHas('messages', [
            'scene_id' => $this->sceneId,
            'body' => 'Абрахам бьёт Порижану.',
            'user_id' => $this->storyteller->id,
            'is_narration' => true,
            'is_ooc' => false,
            'is_system' => false,
            'author_character_id' => null,
            'npc_name' => null,
        ]);

        $listed = $this->actingAs($this->player, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$this->sceneId);

        $listed->assertStatus(200);
        $listed->assertJsonPath('messages.0.is_narration', true);
        $listed->assertJsonPath('messages.0.is_ooc', false);
    }

    public function test_player_cannot_send_narration(): void
    {
        $this->actingAs($this->player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'Игрок пытается повествовать.',
                'scene_id' => $this->sceneId,
                'is_narration' => true,
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('messages', [
            'body' => 'Игрок пытается повествовать.',
        ]);
    }

    public function test_ooc_with_narration_false_succeeds(): void
    {
        $npc = $this->createNpcOnScene();

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'вывывы',
                'scene_id' => $this->sceneId,
                'is_ooc' => true,
                'is_narration' => false,
                'character_id' => $npc->id,
            ])
            ->assertStatus(201)
            ->assertJsonPath('message.is_ooc', true)
            ->assertJsonPath('message.is_narration', false);
    }

    public function test_narration_cannot_be_ooc(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'Смешанный режим.',
                'scene_id' => $this->sceneId,
                'is_narration' => true,
                'is_ooc' => true,
            ])
            ->assertStatus(422);

        $this->assertDatabaseMissing('messages', [
            'body' => 'Смешанный режим.',
        ]);
    }

    public function test_narration_included_in_diary_l0(): void
    {
        $body = 'Абрахам бьёт Порижану.';
        $npc = $this->createNpcOnScene();

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => $body,
                'scene_id' => $this->sceneId,
                'is_narration' => true,
            ])
            ->assertStatus(201);

        $chat = $this->fakeChat();
        $chat->push('Запись L0 по повествованию.');
        $this->fakeEmbeddings();

        $result = app(DiaryWriterService::class)->writeForScene(Scene::query()->findOrFail($this->sceneId));

        $this->assertSame(1, $result->created);
        $userContent = (string) ($chat->requests()[0]['messages'][1]['content'] ?? '');
        $this->assertStringContainsString('[narration] '.$body, $userContent);
        $this->assertStringNotContainsString($this->storyteller->name.':', $userContent);
        $this->assertStringNotContainsString($npc->entity->canonical_name.':', $userContent);
    }

    public function test_narration_in_copilot_context_has_no_author(): void
    {
        $body = 'В зале гаснут свечи.';
        $npc = $this->createNpcOnScene();

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => $body,
                'scene_id' => $this->sceneId,
                'is_narration' => true,
                'character_id' => $npc->id,
            ])
            ->assertStatus(201);

        $request = new ContextRequest(
            npcName: (string) $npc->entity->canonical_name,
            prompt: 'Тестовый промпт для retrieval',
            sceneId: $this->sceneId,
            draftCount: 3,
            storytellerId: $this->storyteller->id,
            gameSessionId: (int) Scene::query()->findOrFail($this->sceneId)->game_session_id,
            characterId: $npc->id,
            pass: ContextPass::Reply,
            maxInputTokens: null,
            maxOutputTokens: 100,
        );

        $build = app(ContextAssembler::class)->assemble($request);
        $userContent = $build->messages[1]['content'];

        $this->assertStringContainsString('[narration] '.$body, $userContent);
        $this->assertStringNotContainsString('[author]', $userContent);
        $this->assertStringNotContainsString($this->storyteller->name, $userContent);
    }

    public function test_toggling_narration_to_ooc_returns_422(): void
    {
        $messageId = $this->postNarration('Нельзя увести в обсуждение.');

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson('/api/messages/'.$messageId.'/ooc', [
                'is_ooc' => true,
            ])
            ->assertStatus(422);

        $this->assertDatabaseHas('messages', [
            'id' => $messageId,
            'is_narration' => true,
            'is_ooc' => false,
        ]);
    }

    public function test_editing_narration_marks_diary_stale(): void
    {
        $messageId = $this->postNarration('Исходный текст.');
        $entry = $this->coverWithDiaryEntry($messageId);

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson('/api/messages/'.$messageId, [
                'body' => 'Исправленный текст.',
            ])
            ->assertStatus(200);

        $this->assertTrue($entry->refresh()->is_stale);
    }

    public function test_deleting_narration_marks_diary_stale(): void
    {
        $messageId = $this->postNarration('Текст, который уберут.');
        $entry = $this->coverWithDiaryEntry($messageId);

        $this->actingAs($this->storyteller, 'sanctum')
            ->deleteJson('/api/messages/'.$messageId)
            ->assertStatus(204);

        $this->assertTrue($entry->refresh()->is_stale);
    }

    private function createNpcOnScene(): Character
    {
        $npcResponse = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Иван',
                'character_type' => 'npc',
            ]);
        $npcResponse->assertStatus(201);

        $npc = Character::query()->with('entity')->findOrFail($npcResponse->json('character.id'));

        SceneParticipant::factory()->create([
            'scene_id' => $this->sceneId,
            'character_id' => $npc->id,
            'role' => SceneParticipantRole::Npc,
            'is_current' => true,
        ]);

        return $npc;
    }

    private function postNarration(string $body): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => $body,
                'scene_id' => $this->sceneId,
                'is_narration' => true,
            ]);
        $response->assertStatus(201);

        return (int) $response->json('message.id');
    }

    private function coverWithDiaryEntry(int $messageId): CharacterDiaryEntry
    {
        $this->npc = $this->createNpcOnScene();

        $entry = $this->createDiaryEntry(
            'Запись',
            fromMessageId: $messageId,
            toMessageId: $messageId,
        );
        $this->assertSame($this->scene->id, $entry->scene_id);
        $this->assertSame($this->npc->id, $entry->character_id);

        return $entry;
    }
}
