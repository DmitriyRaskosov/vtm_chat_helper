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

class AvatarUploadTest extends TestCase
{
    use JoinsChronicle;

    private User $player;

    private Character $character;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->player = User::factory()->create();
        $chronicle = Chronicle::factory()->create();
        $this->joinChronicle($this->player, $chronicle, ChronicleRole::Player);
        $this->character = Character::factory()->player($this->player)->forChronicle($chronicle)->create([
            'canonical_name' => 'Носитель аватара',
        ]);
    }

    public function test_player_uploads_avatar_resized_to_400(): void
    {
        $this->actingAs($this->player, 'sanctum')
            ->post("/api/characters/{$this->character->id}/avatar", [
                'avatar' => UploadedFile::fake()->image('portrait.png', 800, 200),
            ])
            ->assertOk()
            ->assertJsonPath('avatar_path', "avatars/{$this->character->id}.jpg");

        $relativePath = "avatars/{$this->character->id}.jpg";
        Storage::disk('public')->assertExists($relativePath);
        $this->assertSame($relativePath, $this->character->refresh()->avatar_path);

        $size = getimagesize(Storage::disk('public')->path($relativePath));
        $this->assertIsArray($size);
        $this->assertSame(400, $size[0]);
        $this->assertSame(400, $size[1]);
    }

    public function test_avatar_over_40_megabytes_is_rejected(): void
    {
        $this->actingAs($this->player, 'sanctum')
            ->post("/api/characters/{$this->character->id}/avatar", [
                'avatar' => UploadedFile::fake()->image('huge.png')->size(40961),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('avatar');

        $this->assertNull($this->character->refresh()->avatar_path);
        Storage::disk('public')->assertMissing("avatars/{$this->character->id}.jpg");
    }

    public function test_foreign_player_cannot_upload_avatar(): void
    {
        $foreign = User::factory()->create();
        $this->joinChronicle($foreign, $this->character->chronicle, ChronicleRole::Player);

        $this->actingAs($foreign, 'sanctum')
            ->post("/api/characters/{$this->character->id}/avatar", [
                'avatar' => UploadedFile::fake()->image('portrait.png'),
            ])
            ->assertStatus(403);

        $this->assertNull($this->character->refresh()->avatar_path);
    }
}
