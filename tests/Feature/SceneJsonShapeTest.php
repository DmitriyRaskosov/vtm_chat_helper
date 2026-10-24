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

class SceneJsonShapeTest extends TestCase
{
    use JoinsChronicle;

    private const SCENE_KEYS = [
        'id',
        'game_session_id',
        'title',
        'description',
        'position',
        'status',
        'started_at',
        'ended_at',
    ];

    private const GAME_SESSION_SCENE_KEYS = [
        'id',
        'title',
        'description',
        'position',
        'status',
        'started_at',
        'ended_at',
    ];

    private const GAME_SESSION_KEYS = [
        'id',
        'chronicle_id',
        'title',
        'status',
        'active_character_id',
        'active_scene_id',
        'scenes',
    ];

    private const CONTEXT_KEYS = [
        'scene_id',
        'location_entity_id',
        'location_name',
        'atmosphere',
        'situation',
        'storyteller_notes',
        'revision',
        'frozen_revision',
        'updated_by',
        'updated_at',
    ];

    private const PARTICIPANT_KEYS = [
        'id',
        'scene_id',
        'character_id',
        'character_name',
        'character_type',
        'user_id',
        'role',
        'visible',
        'is_current',
        'entered_at',
        'left_at',
    ];

    private User $storyteller;

    private Chronicle $chronicle;

    private GameSession $gameSession;

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
            'title' => 'Сессия',
            'created_by' => $this->storyteller->id,
        ]);
    }

    public function test_post_scene_response_shape(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Новая сцена',
            ]);

        $response->assertStatus(201);
        $scene = $response->json('scene');
        $this->assertSame(self::SCENE_KEYS, array_keys($scene));
    }

    public function test_patch_close_and_activate_scene_shapes(): void
    {
        $sceneId = $this->createSceneId();

        $closed = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$sceneId}/close");
        $closed->assertStatus(200);
        $this->assertSame(self::SCENE_KEYS, array_keys($closed->json('scene')));

        $activated = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$sceneId}/activate");
        $activated->assertStatus(200);
        $this->assertSame(self::SCENE_KEYS, array_keys($activated->json('scene')));
    }

    public function test_game_session_active_and_store_shapes(): void
    {
        $active = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'game-sessions/active'));
        $active->assertStatus(200);
        $this->assertSame(self::GAME_SESSION_KEYS, array_keys($active->json('game_session')));

        $created = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'game-sessions'), ['title' => 'Новая игра']);
        $created->assertStatus(201);

        $session = $created->json('game_session');
        $this->assertSame(self::GAME_SESSION_KEYS, array_keys($session));
        $this->assertNotEmpty($session['scenes']);
        $this->assertSame(self::GAME_SESSION_SCENE_KEYS, array_keys($session['scenes'][0]));

        $activeAfterStore = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'game-sessions/active'));
        $activeAfterStore->assertStatus(200);
        $this->assertSame(self::GAME_SESSION_KEYS, array_keys($activeAfterStore->json('game_session')));
        $activeAfterStore->assertJsonPath('game_session.title', 'Новая игра');
    }

    public function test_scene_context_get_and_put_shapes(): void
    {
        $sceneId = $this->createSceneId();

        $show = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/scenes/{$sceneId}/context");
        $show->assertStatus(200);
        $this->assertSame(self::CONTEXT_KEYS, array_keys($show->json('context')));

        $update = $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/scenes/{$sceneId}/context", [
                'expected_revision' => 0,
                'situation' => 'Ночь',
            ]);
        $update->assertStatus(200);
        $this->assertSame(self::CONTEXT_KEYS, array_keys($update->json('context')));
    }

    public function test_scene_participants_index_and_store_shapes(): void
    {
        $sceneId = $this->createSceneId();

        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Участник',
                'character_type' => 'npc',
            ]);
        $npcId = (int) $npc->json('character.id');

        $store = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/scenes/{$sceneId}/participants", [
                'character_id' => $npcId,
                'role' => 'npc',
            ]);
        $store->assertStatus(201);
        $this->assertSame(self::PARTICIPANT_KEYS, array_keys($store->json('participant')));

        $index = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/scenes/{$sceneId}/participants");
        $index->assertStatus(200);
        $index->assertJsonStructure(['participants' => ['*' => self::PARTICIPANT_KEYS]]);
        $this->assertSame(self::PARTICIPANT_KEYS, array_keys($index->json('participants.0')));
    }

    private function createSceneId(): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Сцена',
            ]);
        $response->assertStatus(201);

        return (int) $response->json('scene.id');
    }
}
