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

class RoleToolsTest extends TestCase
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
        $scene = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$gameSession->id.'/scenes', ['title' => 'Сцена']);
        $this->sceneId = (int) $scene->json('scene.id');
    }

    public function test_participant_store_creates_system_message_with_custom_text(): void
    {
        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/chronicles/'.$this->chronicle->id.'/characters', [
                'canonical_name' => 'Тень',
                'character_type' => 'npc',
            ]);
        $npcId = (int) $npc->json('character.id');

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/scenes/{$this->sceneId}/participants", [
                'character_id' => $npcId,
                'role' => 'npc',
                'system_message' => 'Выходит из тени',
            ])
            ->assertStatus(201);

        $this->assertDatabaseHas('messages', [
            'scene_id' => $this->sceneId,
            'body' => 'Выходит из тени',
            'is_system' => true,
            'author_character_id' => null,
            'user_id' => $this->storyteller->id,
        ]);
    }

    public function test_player_can_edit_only_recent_own_message(): void
    {
        for ($i = 1; $i <= 11; $i++) {
            Message::factory()->create([
                'scene_id' => $this->sceneId,
                'user_id' => $this->player->id,
                'body' => "Сообщение {$i}",
            ]);
        }

        $old = Message::query()->where('scene_id', $this->sceneId)->orderBy('id')->first();
        $recent = Message::query()->where('scene_id', $this->sceneId)->orderByDesc('id')->first();

        $this->actingAs($this->player, 'sanctum')
            ->patchJson("/api/messages/{$old->id}", ['body' => 'Правка'])
            ->assertStatus(403);

        $this->actingAs($this->player, 'sanctum')
            ->patchJson("/api/messages/{$recent->id}", ['body' => 'Свежее'])
            ->assertOk()
            ->assertJsonPath('message.body', 'Свежее');
    }

    public function test_user_notes_upsert_and_fetch_by_scene(): void
    {
        $this->actingAs($this->player, 'sanctum')
            ->putJson('/api/user-notes', [
                'scene_id' => $this->sceneId,
                'content' => 'Моя заметка',
            ])
            ->assertOk()
            ->assertJsonPath('note.content', 'Моя заметка');

        $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/user-notes?scene_id='.$this->sceneId)
            ->assertOk()
            ->assertJsonPath('note.content', 'Моя заметка');
    }

    public function test_storyteller_can_update_character_description_and_inventory(): void
    {
        $npc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/chronicles/'.$this->chronicle->id.'/characters', [
                'canonical_name' => 'Описание-NPC',
                'character_type' => 'npc',
            ]);
        $npcId = (int) $npc->json('character.id');

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/characters/{$npcId}", [
                'description' => 'Высокий вампир',
                'inventory' => 'Кинжал',
            ])
            ->assertOk()
            ->assertJsonPath('character.description', 'Высокий вампир')
            ->assertJsonPath('character.inventory', 'Кинжал');

        $pc = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/chronicles/'.$this->chronicle->id.'/characters', [
                'canonical_name' => 'Игрок',
                'character_type' => 'player',
                'user_id' => $this->player->id,
            ]);
        $pcId = (int) $pc->json('character.id');

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/characters/{$pcId}", [
                'description' => 'Заметное лицо',
                'inventory' => 'Телефон',
            ])
            ->assertOk();

        $this->actingAs($this->player, 'sanctum')
            ->getJson("/api/characters/{$pcId}")
            ->assertOk()
            ->assertJsonPath('character.description', 'Заметное лицо')
            ->assertJsonMissingPath('character.inventory');
    }
}
