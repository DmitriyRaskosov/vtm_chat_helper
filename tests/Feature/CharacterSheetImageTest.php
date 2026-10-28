<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CharacterSheetImageTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->storyteller = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
    }

    public function test_storyteller_can_upload_image(): void
    {
        $npcId = $this->createNpc();
        $file = UploadedFile::fake()->image('charsheet.png');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->post("/api/characters/{$npcId}/sheet-image", [
                'image' => $file,
            ]);

        $response->assertOk();
        $response->assertJsonPath('sheet_image_path', "characters/{$npcId}/sheet.png");

        $character = Character::query()->findOrFail($npcId);
        $this->assertSame("characters/{$npcId}/sheet.png", $character->sheet_image_path);
        Storage::disk('public')->assertExists("characters/{$npcId}/sheet.png");
    }

    public function test_player_cannot_upload_image_for_own_pc(): void
    {
        $player = User::factory()->create();
        $pcId = $this->createPlayerCharacter($player);

        $this->actingAs($player, 'sanctum')
            ->post("/api/characters/{$pcId}/sheet-image", [
                'image' => UploadedFile::fake()->image('charsheet.png'),
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Только рассказчик.');
    }

    public function test_player_cannot_upload_image_for_foreign_character(): void
    {
        $npcId = $this->createNpc();
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $this->actingAs($player, 'sanctum')
            ->post("/api/characters/{$npcId}/sheet-image", [
                'image' => UploadedFile::fake()->image('charsheet.png'),
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Только рассказчик.');
    }

    public function test_player_can_view_own_pc_image(): void
    {
        $player = User::factory()->create();
        $pcId = $this->createPlayerCharacter($player);

        $this->actingAs($this->storyteller, 'sanctum')
            ->post("/api/characters/{$pcId}/sheet-image", [
                'image' => UploadedFile::fake()->image('charsheet.jpg'),
            ])
            ->assertOk();

        $this->actingAs($player, 'sanctum')
            ->get("/api/characters/{$pcId}/sheet-image")
            ->assertOk();
    }

    public function test_player_cannot_view_foreign_character_image(): void
    {
        $npcId = $this->createNpc();
        $player = User::factory()->create();
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $this->actingAs($this->storyteller, 'sanctum')
            ->post("/api/characters/{$npcId}/sheet-image", [
                'image' => UploadedFile::fake()->image('charsheet.png'),
            ])
            ->assertOk();

        $this->actingAs($player, 'sanctum')
            ->get("/api/characters/{$npcId}/sheet-image")
            ->assertStatus(403)
            ->assertJsonPath('message', 'You cannot access this character sheet.');
    }

    public function test_upload_rejects_non_image_file(): void
    {
        $npcId = $this->createNpc();

        $this->actingAs($this->storyteller, 'sanctum')
            ->post("/api/characters/{$npcId}/sheet-image", [
                'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
            ])
            ->assertStatus(422);
    }

    public function test_upload_replaces_old_image(): void
    {
        $npcId = $this->createNpc();

        $this->actingAs($this->storyteller, 'sanctum')
            ->post("/api/characters/{$npcId}/sheet-image", [
                'image' => UploadedFile::fake()->image('first.png'),
            ])
            ->assertOk();

        Storage::disk('public')->assertExists("characters/{$npcId}/sheet.png");

        $this->actingAs($this->storyteller, 'sanctum')
            ->post("/api/characters/{$npcId}/sheet-image", [
                'image' => UploadedFile::fake()->image('second.webp'),
            ])
            ->assertOk()
            ->assertJsonPath('sheet_image_path', "characters/{$npcId}/sheet.webp");

        Storage::disk('public')->assertMissing("characters/{$npcId}/sheet.png");
        Storage::disk('public')->assertExists("characters/{$npcId}/sheet.webp");
    }

    private function createNpc(): int
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'NPC для чарлиста',
                'character_type' => 'npc',
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }

    private function createPlayerCharacter(User $player): int
    {
        $this->joinChronicle($player, $this->chronicle, ChronicleRole::Player);

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'PC для чарлиста',
                'character_type' => 'player',
                'user_id' => $player->id,
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }
}
