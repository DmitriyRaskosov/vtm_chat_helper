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

class ChatJsonShapeTest extends TestCase
{
    use JoinsChronicle;

    private const MESSAGE_KEYS = [
        'id',
        'scene_id',
        'body',
        'author',
        'user_name',
        'mine',
        'npc_name',
        'author_character_id',
        'is_ooc',
        'is_deleted',
        'created_at',
    ];

    private User $storyteller;

    private User $player;

    private Chronicle $chronicle;

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
            'title' => 'Сессия',
            'created_by' => $this->storyteller->id,
        ]);

        $scene = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$gameSession->id.'/scenes', [
                'title' => 'Сцена чата',
            ]);
        $scene->assertStatus(201);
        $this->sceneId = (int) $scene->json('scene.id');
    }

    public function test_get_messages_item_shape(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'Текст',
                'scene_id' => $this->sceneId,
            ])
            ->assertStatus(201);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$this->sceneId);

        $response->assertStatus(200);
        $response->assertJsonStructure(['messages' => ['*' => self::MESSAGE_KEYS]]);

        $item = $response->json('messages.0');
        $this->assertSame(self::MESSAGE_KEYS, array_keys($item));
        $this->assertIsBool($item['is_deleted']);
        $this->assertFalse($item['is_deleted']);
        $this->assertIsBool($item['is_ooc']);
        $this->assertIsBool($item['mine']);
    }

    public function test_message_mine_true_for_author_and_false_for_other_player(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'От мастера',
                'scene_id' => $this->sceneId,
            ])
            ->assertStatus(201);

        $asAuthor = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$this->sceneId);
        $asAuthor->assertJsonPath('messages.0.mine', true);

        $asPlayer = $this->actingAs($this->player, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$this->sceneId);
        $asPlayer->assertJsonPath('messages.0.mine', false);
    }

    public function test_post_message_response_shape(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'Новое',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(201);
        $message = $response->json('message');
        $this->assertSame(self::MESSAGE_KEYS, array_keys($message));
    }

    public function test_toggle_ooc_and_restore_message_shapes(): void
    {
        $create = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'Для правок',
                'scene_id' => $this->sceneId,
            ]);
        $create->assertStatus(201);
        $messageId = (int) $create->json('message.id');

        $ooc = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/messages/{$messageId}/ooc", ['is_ooc' => true]);
        $ooc->assertStatus(200);
        $this->assertSame(self::MESSAGE_KEYS, array_keys($ooc->json('message')));

        $this->actingAs($this->storyteller, 'sanctum')
            ->deleteJson("/api/messages/{$messageId}")
            ->assertStatus(204);

        $deletedList = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$this->sceneId.'&include_deleted=1');
        $deletedList->assertJsonPath('messages.0.is_deleted', true);

        $restored = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/messages/{$messageId}/restore");
        $restored->assertStatus(200);
        $this->assertSame(self::MESSAGE_KEYS, array_keys($restored->json('message')));
        $restored->assertJsonPath('message.is_deleted', false);
    }
}
