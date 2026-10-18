<?php

namespace Tests\Feature;

use App\Character\Exceptions\BiographyUnchangedException;
use App\Enums\ChronicleRole;
use App\Enums\GameSessionStatus;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class DomainExceptionMappingTest extends TestCase
{
    use JoinsChronicle;

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
            'title' => 'Текущая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Сессия',
            'created_by' => $this->storyteller->id,
        ]);
    }

    public function test_mixed_chronicle_on_character_store_returns_409(): void
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

    public function test_mixed_chronicle_on_character_update_returns_409(): void
    {
        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        $foreignSire = Character::factory()
            ->forChronicle($otherChronicle)
            ->create(['canonical_name' => 'Чужой сир']);

        $characterId = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Герой',
                'character_type' => 'npc',
            ])
            ->assertStatus(201)
            ->json('character.id');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/characters/{$characterId}", [
                'sire_character_id' => $foreignSire->id,
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'World entities must belong to the same chronicle.');
    }

    public function test_mixed_chronicle_on_scene_participant_store_returns_409(): void
    {
        $otherChronicle = Chronicle::factory()->create([
            'title' => 'Другая хроника',
            'created_by' => $this->storyteller->id,
        ]);
        GameSession::factory()->active()->create([
            'chronicle_id' => $otherChronicle->id,
            'title' => 'Другая сессия',
            'status' => GameSessionStatus::Active,
            'created_by' => $this->storyteller->id,
        ]);

        $foreignNpc = Character::factory()
            ->forChronicle($otherChronicle)
            ->create(['canonical_name' => 'Чужой NPC']);

        $sceneId = $this->createScene();

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/scenes/{$sceneId}/participants", [
                'character_id' => $foreignNpc->id,
                'role' => 'npc',
            ]);

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'World entities must belong to the same chronicle.');
    }

    public function test_biography_unchanged_exception_is_invalid_input_with_422_status_code(): void
    {
        $exception = new BiographyUnchangedException;

        $this->assertSame(422, $exception->statusCode());
        $this->assertSame('Biography is unchanged.', $exception->getMessage());
    }

    public function test_unchanged_biography_update_returns_200(): void
    {
        $characterId = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Биограф',
                'character_type' => 'npc',
            ])
            ->assertStatus(201)
            ->json('character.id');

        $payload = ['summary' => 'Кратко о герое'];

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/characters/{$characterId}/biography", $payload)
            ->assertStatus(200);

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson("/api/characters/{$characterId}/biography", $payload)
            ->assertStatus(200);
    }

    public function test_invalid_character_input_returns_422_with_errors_object(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Первый',
                'character_type' => 'npc',
            ])
            ->assertStatus(201);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'первый',
                'character_type' => 'npc',
            ]);

        $response->assertStatus(422);
        $response->assertJsonPath('message', 'This name is already used in the chronicle.');
        $response->assertJsonStructure(['message', 'errors']);
    }

    public function test_archiving_character_in_closed_scene_returns_200(): void
    {
        $npcId = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'В закрытой сцене',
                'character_type' => 'npc',
            ])
            ->assertStatus(201)
            ->json('character.id');

        $sceneId = $this->createScene();

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/scenes/{$sceneId}/participants", [
                'character_id' => $npcId,
                'role' => 'npc',
            ])
            ->assertStatus(201);

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$sceneId}/close")
            ->assertStatus(200);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/characters/{$npcId}/archive")
            ->assertStatus(200)
            ->assertJsonPath('character.id', $npcId);
    }

    private function createScene(): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$this->gameSession->id.'/scenes', [
                'title' => 'Сцена',
            ]);

        $response->assertStatus(201);

        return (int) $response->json('scene.id');
    }
}
