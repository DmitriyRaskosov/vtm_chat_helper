<?php

namespace Tests\Feature;

use App\Context\TokenEstimator;
use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class MessageTokenEstimateTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

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
    }

    public function test_saving_message_sets_token_estimate_and_version(): void
    {
        $estimator = app(TokenEstimator::class);
        $expectedEstimate = $estimator->estimate('Текст для оценки');
        $expectedVersion = $estimator->version();

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'Текст для оценки',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(201);

        $messageId = (int) $response->json('message.id');
        $message = Message::query()->findOrFail($messageId);

        $this->assertSame($expectedEstimate, $message->token_estimate);
        $this->assertSame($expectedVersion, $message->token_estimator_version);
    }

    public function test_saving_without_body_change_does_not_recalculate_tokens(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'body' => 'Неподвижный текст',
                'scene_id' => $this->sceneId,
            ]);
        $response->assertStatus(201);

        $message = Message::query()->findOrFail((int) $response->json('message.id'));
        $originalEstimate = $message->token_estimate;
        $originalVersion = $message->token_estimator_version;

        $message->is_ooc = ! $message->is_ooc;
        $message->save();

        $message->refresh();

        $this->assertSame($originalEstimate, $message->token_estimate);
        $this->assertSame($originalVersion, $message->token_estimator_version);
    }
}
