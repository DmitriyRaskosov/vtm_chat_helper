<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\SceneContext;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class SceneContextImageTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

    private GameSession $gameSession;

    private int $sceneId;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->storyteller = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);

        $this->gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);

        $created = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Локация',
            ]);
        $created->assertStatus(201);
        $this->sceneId = (int) $created->json('scene.id');
    }

    public function test_storyteller_can_upload_location_image(): void
    {
        $file = UploadedFile::fake()->image('place.png');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->post("/api/scenes/{$this->sceneId}/image", [
                'image' => $file,
            ]);

        $response->assertOk();
        $response->assertJsonPath('location_image_path', "scenes/{$this->sceneId}/location.png");

        $context = SceneContext::query()->where('scene_id', $this->sceneId)->first();
        $this->assertNotNull($context);
        $this->assertSame("scenes/{$this->sceneId}/location.png", $context->location_image_path);
        Storage::disk('public')->assertExists("scenes/{$this->sceneId}/location.png");
    }

    public function test_player_cannot_upload_location_image(): void
    {
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $this->actingAs($player, 'sanctum')
            ->post("/api/scenes/{$this->sceneId}/image", [
                'image' => UploadedFile::fake()->image('place.png'),
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Только рассказчик.');
    }

    public function test_player_can_view_location_image(): void
    {
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $this->actingAs($this->storyteller, 'sanctum')
            ->post("/api/scenes/{$this->sceneId}/image", [
                'image' => UploadedFile::fake()->image('place.jpg'),
            ])
            ->assertOk();

        $this->actingAs($player, 'sanctum')
            ->get("/api/scenes/{$this->sceneId}/image")
            ->assertOk();
    }

    public function test_show_returns_404_when_no_image(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->get("/api/scenes/{$this->sceneId}/image")
            ->assertStatus(404);
    }
}
