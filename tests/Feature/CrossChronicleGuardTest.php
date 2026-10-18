<?php

namespace Tests\Feature;

use App\Diary\DiaryEntryRepository;
use App\Diary\Exceptions\DiaryChronicleMismatchException;
use App\Enums\ChronicleRole;
use App\Enums\GameSessionStatus;
use App\Enums\SceneParticipantRole;
use App\Messages\MessageService;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use App\Rag\VectorLiteral;
use App\World\MixedChronicleException;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CrossChronicleGuardTest extends TestCase
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
            'title' => 'Текущая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Сессия',
            'created_by' => $this->storyteller->id,
        ]);

        $created = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Сцена',
            ]);
        $created->assertStatus(201);
        $this->sceneId = (int) $created->json('scene.id');
    }

    public function test_foreign_chronicle_participant_is_rejected_and_not_persisted(): void
    {
        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $foreignNpc = Character::factory()
            ->forChronicle($otherChronicle)
            ->create(['canonical_name' => 'Чужой NPC']);

        $before = SceneParticipant::query()->count();

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/scenes/'.$this->sceneId.'/participants', [
                'character_id' => $foreignNpc->id,
                'role' => 'npc',
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'World entities must belong to the same chronicle.');
        $this->assertSame($before, SceneParticipant::query()->count());
    }

    public function test_foreign_chronicle_sire_is_rejected_on_character_create(): void
    {
        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $foreignSire = Character::factory()
            ->forChronicle($otherChronicle)
            ->create(['canonical_name' => 'Чужой сир']);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Потомок',
                'character_type' => 'npc',
                'sire_character_id' => $foreignSire->id,
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'World entities must belong to the same chronicle.');
    }

    public function test_foreign_chronicle_message_author_returns_409_and_does_not_persist(): void
    {
        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $otherChronicle, ChronicleRole::Storyteller);

        $foreignNpc = Character::factory()
            ->forChronicle($otherChronicle)
            ->create(['canonical_name' => 'Чужой NPC']);

        $before = Message::query()->count();

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'X',
                'scene_id' => $this->sceneId,
                'character_id' => $foreignNpc->id,
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Character does not belong to this chronicle.');
        $this->assertSame($before, Message::query()->count());
    }

    public function test_foreign_chronicle_scene_id_on_messages_index_does_not_leak_messages(): void
    {
        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $otherChronicle, ChronicleRole::Storyteller);

        $otherSession = GameSession::factory()->active()->create([
            'chronicle_id' => $otherChronicle->id,
            'title' => 'Другая сессия',
            'status' => GameSessionStatus::Active,
            'created_by' => $this->storyteller->id,
        ]);

        $foreignScene = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$otherSession->id.'/scenes', [
                'title' => 'Чужая сцена',
            ]);
        $foreignScene->assertStatus(201);
        $foreignSceneId = (int) $foreignScene->json('scene.id');

        Message::factory()->create([
            'scene_id' => $foreignSceneId,
            'user_id' => $this->storyteller->id,
            'body' => 'Секрет',
        ]);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$foreignSceneId);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'There is no available scene in the active game session.');
    }

    public function test_message_service_resolve_world_author_rejects_character_from_other_chronicle(): void
    {
        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $foreignNpc = Character::factory()
            ->forChronicle($otherChronicle)
            ->create(['canonical_name' => 'Чужой NPC']);

        $scene = Scene::query()->with('gameSession')->findOrFail($this->sceneId);

        $this->expectException(MixedChronicleException::class);

        $this->app->make(MessageService::class)->resolveWorldAuthor(
            $scene,
            $this->storyteller,
            $foreignNpc->id,
        );
    }

    public function test_diary_entry_repository_rejects_npc_from_other_chronicle(): void
    {
        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $foreignNpc = Character::factory()
            ->forChronicle($otherChronicle)
            ->create(['canonical_name' => 'Чужой NPC']);

        $localNpcId = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Локальный NPC',
                'character_type' => 'npc',
            ])
            ->assertStatus(201)
            ->json('character.id');

        $scene = Scene::query()->with('gameSession')->findOrFail($this->sceneId);
        $participant = SceneParticipant::factory()->create([
            'scene_id' => $scene->id,
            'character_id' => $localNpcId,
            'role' => SceneParticipantRole::Npc,
        ]);

        $before = CharacterDiaryEntry::query()->count();

        try {
            $this->app->make(DiaryEntryRepository::class)->insertL0(
                $foreignNpc,
                $scene,
                $participant,
                1,
                1,
                'Запись',
                null,
                null,
                VectorLiteral::fromArray(array_fill(0, 1024, 0.0)),
            );
            $this->fail('Expected DiaryChronicleMismatchException.');
        } catch (DiaryChronicleMismatchException $e) {
            $this->assertSame(
                'Diary entry must belong to the same chronicle as the scene.',
                $e->getMessage(),
            );
        }

        $this->assertSame($before, CharacterDiaryEntry::query()->count());
    }
}
