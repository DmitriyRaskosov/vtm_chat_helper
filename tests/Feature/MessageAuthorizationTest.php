<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class MessageAuthorizationTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private User $player;

    private int $sceneId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->player = User::factory()->create();

        $chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $chronicle, ChronicleRole::Storyteller);
        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);
        $scene = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$gameSession->id.'/scenes', ['title' => 'Сцена']);
        $this->sceneId = (int) $scene->json('scene.id');
    }

    public function test_player_cannot_edit_others_message(): void
    {
        $message = Message::factory()->create([
            'scene_id' => $this->sceneId,
            'user_id' => $this->storyteller->id,
            'body' => 'Чужое',
        ]);

        $this->actingAs($this->player, 'sanctum')
            ->deleteJson("/api/messages/{$message->id}")
            ->assertStatus(403)
            ->assertJsonPath('message', 'You cannot edit this message.');
    }

    public function test_storyteller_can_edit_any_message(): void
    {
        $message = Message::factory()->create([
            'scene_id' => $this->sceneId,
            'user_id' => $this->player->id,
            'body' => 'Реплика игрока',
        ]);

        $this->actingAs($this->storyteller, 'sanctum')
            ->deleteJson("/api/messages/{$message->id}")
            ->assertStatus(204);
    }
}
