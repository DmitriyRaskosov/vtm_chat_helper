<?php

namespace Tests\Feature;

use App\Diary\DiaryRetrievalService;
use App\Enums\GameSessionStatus;
use App\Enums\SceneParticipantRole;
use App\Enums\SceneStatus;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MessageEditTest extends TestCase
{
    private User $storyteller;
    private User $player;
    private Chronicle $chronicle;
    private GameSession $gameSession;
    private Scene $scene;
    private Character $npc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->storyteller()->create();
        $this->player = User::factory()->create();

        $this->chronicle = Chronicle::query()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);

        $this->gameSession = GameSession::query()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Сессия',
            'status' => GameSessionStatus::Active,
            'created_by' => $this->storyteller->id,
        ]);

        $this->scene = Scene::query()->create([
            'game_session_id' => $this->gameSession->id,
            'position' => 1,
            'title' => 'Сцена',
            'status' => SceneStatus::Active,
        ]);

        $npcResponse = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Абрахам',
                'character_type' => 'npc',
                'chronicle_id' => $this->chronicle->id,
            ]);
        $npcResponse->assertStatus(201);

        $this->npc = Character::query()->findOrFail($npcResponse->json('character.id'));

        SceneParticipant::query()->create([
            'scene_id' => $this->scene->id,
            'chronicle_id' => $this->chronicle->id,
            'character_id' => $this->npc->id,
            'role' => SceneParticipantRole::Npc,
            'is_current' => true,
            'entered_at' => now(),
        ]);
    }

    public function test_storyteller_can_soft_delete_any_message(): void
    {
        $message = $this->createMessage($this->player, 'Реплика игрока');

        $this->actingAs($this->storyteller, 'sanctum')
            ->deleteJson("/api/messages/{$message->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }

    public function test_player_can_soft_delete_own_message(): void
    {
        $message = $this->createMessage($this->player, 'Своё сообщение');

        $this->actingAs($this->player, 'sanctum')
            ->deleteJson("/api/messages/{$message->id}")
            ->assertStatus(204);

        $this->assertSoftDeleted('messages', ['id' => $message->id]);
    }

    public function test_player_cannot_soft_delete_others_message(): void
    {
        $message = $this->createMessage($this->storyteller, 'Чужое сообщение');

        $this->actingAs($this->player, 'sanctum')
            ->deleteJson("/api/messages/{$message->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'deleted_at' => null,
        ]);
    }

    public function test_restore_message_works(): void
    {
        $message = $this->createMessage($this->player, 'Реплика');
        $message->delete();

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/messages/{$message->id}/restore")
            ->assertStatus(200)
            ->assertJsonPath('message.is_deleted', false);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'deleted_at' => null,
        ]);
    }

    public function test_toggle_ooc_endpoint(): void
    {
        $message = $this->createMessage($this->player, 'Реплика');
        $this->assertFalse((bool) $message->is_ooc);

        $this->actingAs($this->player, 'sanctum')
            ->patchJson("/api/messages/{$message->id}/ooc", ['is_ooc' => true])
            ->assertStatus(200)
            ->assertJsonPath('message.is_ooc', true);

        $this->assertDatabaseHas('messages', [
            'id' => $message->id,
            'is_ooc' => true,
        ]);
    }

    public function test_soft_delete_marks_overlapping_diary_entries_stale(): void
    {
        $first = $this->createMessage($this->storyteller, 'A');
        $target = $this->createMessage($this->storyteller, 'B');
        $last = $this->createMessage($this->storyteller, 'C');

        $inside = $this->createDiaryEntry(
            'Внутри диапазона',
            level: 0,
            fromId: (int) $first->id,
            toId: (int) $last->id,
        );
        $before = $this->createDiaryEntry(
            'Раньше диапазона',
            level: 0,
            fromId: 1,
            toId: (int) $first->id - 1,
        );

        $this->actingAs($this->storyteller, 'sanctum')
            ->deleteJson("/api/messages/{$target->id}")
            ->assertStatus(204);

        $this->assertTrue($inside->refresh()->is_stale);
        $this->assertFalse($before->refresh()->is_stale);
    }

    public function test_toggle_ooc_marks_diary_stale(): void
    {
        $message = $this->createMessage($this->storyteller, 'Реплика');

        $entry = $this->createDiaryEntry(
            'Запись',
            level: 0,
            fromId: (int) $message->id,
            toId: (int) $message->id,
        );

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/messages/{$message->id}/ooc", ['is_ooc' => true])
            ->assertStatus(200);

        $this->assertTrue($entry->refresh()->is_stale);
    }

    public function test_retrieval_skips_stale_entries(): void
    {
        $this->createDiaryEntry('Живая', level: 0, stale: false);
        $this->createDiaryEntry('Устаревшая', level: 0, stale: true);

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve($this->npc, '', limit: 5);

        $this->assertCount(1, $result);
        $this->assertSame('Живая', trim($result->first()->entry));
    }

    private function createMessage(User $author, string $body): Message
    {
        $response = $this->actingAs($author, 'sanctum')
            ->postJson('/api/messages', [
                'body' => $body,
                'scene_id' => $this->scene->id,
            ]);
        $response->assertStatus(201);

        return Message::query()->findOrFail($response->json('message.id'));
    }

    private function createDiaryEntry(
        string $text,
        int $level,
        int $fromId = 1,
        int $toId = 1,
        bool $stale = false,
    ): CharacterDiaryEntry {
        $vector = '['.implode(',', array_fill(0, 1024, '0.1')).']';

        $id = DB::table('character_diary_entries')->insertGetId([
            'character_id' => $this->npc->id,
            'chronicle_id' => $this->chronicle->id,
            'scene_id' => $this->scene->id,
            'level' => $level,
            'from_message_id' => $fromId,
            'to_message_id' => $toId,
            'entry' => $text,
            'embedding' => DB::raw("'{$vector}'::vector"),
            'is_stale' => $stale,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return CharacterDiaryEntry::query()->findOrFail($id);
    }
}