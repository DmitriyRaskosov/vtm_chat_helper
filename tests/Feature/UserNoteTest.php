<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\User;
use App\Models\UserNote;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class UserNoteTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private User $player;

    private User $otherPlayer;

    private Chronicle $chronicle;

    private int $playerCharacterId;

    private int $npcId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->player = User::factory()->create();
        $this->otherPlayer = User::factory()->create();

        $this->chronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->player, $this->chronicle, ChronicleRole::Player);
        $this->joinChronicle($this->otherPlayer, $this->chronicle, ChronicleRole::Player);

        $pc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/chronicles/'.$this->chronicle->id.'/characters', [
                'canonical_name' => 'Игрок',
                'character_type' => 'player',
                'user_id' => $this->player->id,
            ]);
        $this->playerCharacterId = (int) $pc->json('character.id');

        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/chronicles/'.$this->chronicle->id.'/characters', [
                'canonical_name' => 'NPC',
                'character_type' => 'npc',
            ]);
        $this->npcId = (int) $npc->json('character.id');
    }

    private function noteUri(int $characterId): string
    {
        return '/api/chronicles/'.$this->chronicle->id.'/characters/'.$characterId.'/note';
    }

    public function test_user_cannot_read_other_users_notes(): void
    {
        $this->actingAs($this->player, 'sanctum')
            ->putJson($this->noteUri($this->playerCharacterId), ['body' => 'Секрет игрока'])
            ->assertOk();

        $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->noteUri($this->playerCharacterId))
            ->assertOk()
            ->assertJsonPath('note', null);

        $this->assertDatabaseHas('user_notes', [
            'user_id' => $this->player->id,
            'character_id' => $this->playerCharacterId,
            'body' => 'Секрет игрока',
        ]);
    }

    public function test_player_can_write_to_own_pc_only(): void
    {
        $this->actingAs($this->player, 'sanctum')
            ->putJson($this->noteUri($this->playerCharacterId), ['body' => 'Моя заметка'])
            ->assertOk()
            ->assertJsonPath('note.body', 'Моя заметка');

        $this->actingAs($this->player, 'sanctum')
            ->putJson($this->noteUri($this->npcId), ['body' => 'Запрещено'])
            ->assertForbidden();

        $this->actingAs($this->otherPlayer, 'sanctum')
            ->getJson($this->noteUri($this->playerCharacterId))
            ->assertForbidden();
    }

    public function test_storyteller_can_write_to_npc(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson($this->noteUri($this->npcId), ['body' => 'Секреты NPC'])
            ->assertOk()
            ->assertJsonPath('note.body', 'Секреты NPC');

        $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->noteUri($this->npcId))
            ->assertOk()
            ->assertJsonPath('note.body', 'Секреты NPC');
    }

    public function test_upsert_note(): void
    {
        $this->actingAs($this->player, 'sanctum')
            ->putJson($this->noteUri($this->playerCharacterId), ['body' => 'Версия 1'])
            ->assertOk();

        $this->actingAs($this->player, 'sanctum')
            ->putJson($this->noteUri($this->playerCharacterId), ['body' => 'Версия 2'])
            ->assertOk()
            ->assertJsonPath('note.body', 'Версия 2');

        $this->assertSame(1, UserNote::query()->where('user_id', $this->player->id)->count());
        $this->assertDatabaseHas('user_notes', [
            'user_id' => $this->player->id,
            'character_id' => $this->playerCharacterId,
            'chronicle_id' => $this->chronicle->id,
            'body' => 'Версия 2',
        ]);
    }
}
