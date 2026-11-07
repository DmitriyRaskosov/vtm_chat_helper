<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Enums\SceneParticipantRole;
use App\Events\CharacterStatsUpdated;
use App\Events\MessageSent;
use App\Events\UserTyping;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\SceneParticipant;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Support\Facades\Event;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class WebSocketBroadcastTest extends TestCase
{
    use JoinsChronicle;

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
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->player, $this->chronicle, ChronicleRole::Player);

        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);

        $created = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$gameSession->id.'/scenes', [
                'title' => 'Сцена WS',
            ]);
        $created->assertStatus(201);
        $this->sceneId = (int) $created->json('scene.id');
    }

    public function test_sending_message_dispatches_message_sent(): void
    {
        Event::fake([MessageSent::class]);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'Сообщение по WS',
                'scene_id' => $this->sceneId,
            ])
            ->assertStatus(201);

        Event::assertDispatched(MessageSent::class, function (MessageSent $event): bool {
            return $event->sceneId === $this->sceneId
                && ($event->message['body'] ?? null) === 'Сообщение по WS';
        });
    }

    public function test_updating_character_stats_dispatches_character_stats_updated(): void
    {
        $pcId = $this->createPlayerCharacter($this->player);

        SceneParticipant::factory()->create([
            'scene_id' => $this->sceneId,
            'chronicle_id' => $this->chronicle->id,
            'character_id' => $pcId,
            'role' => SceneParticipantRole::Player,
            'is_current' => true,
        ]);

        Event::fake([CharacterStatsUpdated::class]);

        $this->actingAs($this->player, 'sanctum')
            ->patchJson("/api/characters/{$pcId}/stats", [
                'stats' => [
                    'blood_pool' => [1, 1, 1, 0, 0, 0, 0, 0, 0, 0],
                    'health' => [
                        'level' => 0,
                        'damage' => 'bashing',
                        'penalty' => 0,
                    ],
                ],
            ])
            ->assertOk();

        Event::assertDispatched(CharacterStatsUpdated::class, function (CharacterStatsUpdated $event) use ($pcId): bool {
            return $event->sceneId === $this->sceneId
                && $event->characterId === $pcId
                && isset($event->statsPayload['stats']);
        });
    }

    public function test_typing_endpoint_dispatches_user_typing(): void
    {
        Event::fake([UserTyping::class]);

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/scenes/{$this->sceneId}/typing", ['is_ooc' => true])
            ->assertNoContent();

        Event::assertDispatched(UserTyping::class, function (UserTyping $event): bool {
            return $event->sceneId === $this->sceneId
                && $event->userId === $this->player->id
                && $event->isOoc === true;
        });
    }

    public function test_user_cannot_subscribe_to_scene_channel_of_foreign_chronicle(): void
    {
        $this->useReverbBroadcasterForAuth();

        $outsider = User::factory()->create();
        $otherChronicle = Chronicle::factory()->create([
            'created_by' => $outsider->id,
        ]);
        $this->joinChronicle($outsider, $otherChronicle, ChronicleRole::Player);

        $this->assertDatabaseMissing('chronicle_members', [
            'chronicle_id' => $this->chronicle->id,
            'user_id' => $outsider->id,
        ]);

        $this->actingAs($outsider, 'sanctum')
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1.1',
                'channel_name' => 'private-scene.'.$this->sceneId,
            ])
            ->assertForbidden();
    }

    private function useReverbBroadcasterForAuth(): void
    {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-reverb-key',
            'broadcasting.connections.reverb.secret' => 'test-reverb-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
        ]);
    }

    private function createPlayerCharacter(User $player): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'PC WS',
                'character_type' => 'player',
                'user_id' => $player->id,
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }
}
