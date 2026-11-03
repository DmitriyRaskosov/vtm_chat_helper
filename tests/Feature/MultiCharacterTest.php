<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\CanonClan;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\Message;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class MultiCharacterTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private User $player;

    private Chronicle $chronicle;

    private CanonClan $clan;

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
        $this->clan = CanonClan::query()->where('slug', 'tremere')->firstOrFail();
    }

    public function test_player_can_create_second_character(): void
    {
        $this->createCharacter('Первый');

        $second = $this->actingAs($this->player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Второй',
                'clan_id' => $this->clan->id,
            ]);

        $second->assertStatus(201);
        $second->assertJsonPath('character.character_type', 'player');
        $second->assertJsonPath('character.user_id', $this->player->id);

        $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/profile/characters')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_player_can_archive_own_character_and_profile_keeps_it(): void
    {
        $characterId = $this->createCharacter('Архивный');

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/characters/{$characterId}/archive")
            ->assertOk()
            ->assertJsonPath('character.is_active', false);

        $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/profile/characters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $characterId)
            ->assertJsonPath('data.0.is_active', false);
    }

    public function test_player_soft_deletes_archived_character_and_profile_hides_it(): void
    {
        $characterId = $this->createCharacter('Удаляемый');

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/characters/{$characterId}/archive")
            ->assertOk();

        $this->actingAs($this->player, 'sanctum')
            ->deleteJson("/api/characters/{$characterId}")
            ->assertNoContent();

        $deleted = Character::withTrashed()->find($characterId);
        $this->assertNotNull($deleted);
        $this->assertNotNull($deleted->deleted_at);

        $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/profile/characters')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_player_cannot_delete_character_that_has_messages(): void
    {
        $characterId = $this->createCharacter('С сообщениями');

        $this->actingAs($this->player, 'sanctum')
            ->postJson("/api/characters/{$characterId}/archive")
            ->assertOk();

        Message::factory()->create([
            'user_id' => $this->player->id,
            'author_character_id' => $characterId,
        ]);

        $this->actingAs($this->player, 'sanctum')
            ->deleteJson("/api/characters/{$characterId}")
            ->assertStatus(422);

        $this->assertNull(Character::withTrashed()->find($characterId)?->deleted_at);
    }

    private function createCharacter(string $name): int
    {
        $response = $this->actingAs($this->player, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => $name,
                'clan_id' => $this->clan->id,
            ]);
        $response->assertStatus(201);

        return (int) $response->json('character.id');
    }
}
