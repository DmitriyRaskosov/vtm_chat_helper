<?php

namespace Tests\Feature;

use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FactoriesTest extends TestCase
{
    public function test_user_factory_creates_user(): void
    {
        $user = User::factory()->create();

        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    public function test_chronicle_factory_creates_chronicle(): void
    {
        $chronicle = Chronicle::factory()->create();

        $this->assertDatabaseHas('chronicles', ['id' => $chronicle->id]);
    }

    public function test_game_session_factory_creates_session(): void
    {
        $session = GameSession::factory()->active()->create();

        $this->assertDatabaseHas('game_sessions', ['id' => $session->id, 'status' => 'active']);
    }

    public function test_scene_factory_creates_scene(): void
    {
        $scene = Scene::factory()->active()->create();

        $this->assertDatabaseHas('scenes', ['id' => $scene->id, 'status' => 'active']);
    }

    public function test_message_factory_creates_message(): void
    {
        $message = Message::factory()->create();

        $this->assertDatabaseHas('messages', ['id' => $message->id]);
    }

    public function test_scene_participant_factory_creates_participant(): void
    {
        $participant = SceneParticipant::factory()->npc()->create();

        $this->assertDatabaseHas('scene_participants', ['id' => $participant->id]);
    }

    public function test_character_factory_npc_creates_world_entity_with_same_id(): void
    {
        $character = Character::factory()->npc()->create();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'character_type' => CharacterType::Npc->value,
            'user_id' => null,
        ]);
        $this->assertDatabaseHas('world_entities', [
            'id' => $character->id,
            'entity_type' => WorldEntityType::Character->value,
            'chronicle_id' => $character->chronicle_id,
        ]);
    }

    public function test_character_factory_player_links_user(): void
    {
        $character = Character::factory()->player()->create();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'character_type' => CharacterType::Player->value,
            'user_id' => $character->user_id,
        ]);
        $this->assertDatabaseHas('world_entities', ['id' => $character->id]);
    }

    public function test_player_without_user_id_fails_check_constraint(): void
    {
        $this->expectException(QueryException::class);

        Character::factory()->player()->create(['user_id' => null]);
    }

    public function test_world_entity_row_cannot_be_deleted_for_factory_character(): void
    {
        $character = Character::factory()->npc()->create();

        try {
            DB::table('world_entities')->where('id', $character->id)->delete();
            $this->fail('Expected PostgreSQL trigger to block world_entities delete.');
        } catch (QueryException $e) {
            $this->assertStringContainsString(
                'World entities cannot be deleted',
                $e->getMessage(),
            );
        }
    }
}
