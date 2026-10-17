<?php

namespace Tests\Feature;

use App\Diary\DiarySummarizerService;
use App\Diary\DiaryWriterService;
use App\Enums\SceneParticipantRole;
use App\Jobs\SummarizeSceneDiaryJob;
use App\Jobs\WriteDiaryJob;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesDiaryEntries;
use Tests\Support\CreatesSceneMessages;
use Tests\TestCase;

class DiaryLlmServicesTest extends TestCase
{
    use CreatesDiaryEntries;
    use CreatesSceneMessages;

    private User $storyteller;

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

        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);

        $this->gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Тестовая сессия',
            'created_by' => $this->storyteller->id,
        ]);

        $this->scene = Scene::factory()->active()->create([
            'game_session_id' => $this->gameSession->id,
            'position' => 1,
            'title' => 'Сцена',
        ]);

        $npcResponse = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Абрахам',
                'character_type' => 'npc',
                'chronicle_id' => $this->chronicle->id,
            ]);
        $npcResponse->assertStatus(201);

        $this->npc = Character::query()->findOrFail($npcResponse->json('character.id'));

        SceneParticipant::factory()->create([
            'scene_id' => $this->scene->id,
            'chronicle_id' => $this->chronicle->id,
            'character_id' => $this->npc->id,
            'role' => SceneParticipantRole::Npc,
            'is_current' => true,
            'entered_at' => now(),
        ]);
    }

    public function test_write_diary_job_not_dispatched_when_pending_below_write_threshold(): void
    {
        config(['diary.write_threshold' => 5]);
        Queue::fake();

        foreach (range(1, 3) as $i) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson('/api/messages', [
                    'scene_id' => $this->scene->id,
                    'body' => "Сообщение {$i}",
                ])
                ->assertStatus(201);
        }

        Queue::assertNotPushed(WriteDiaryJob::class);

        $participant = SceneParticipant::query()
            ->where('scene_id', $this->scene->id)
            ->where('character_id', $this->npc->id)
            ->first();
        $this->assertNull($participant->last_diary_message_id);
    }

    public function test_write_for_scene_creates_no_entry_when_no_new_messages(): void
    {
        $this->fakeChat()->push('Не должен вызываться.');
        $this->fakeEmbeddings();

        $participant = SceneParticipant::query()
            ->where('scene_id', $this->scene->id)
            ->where('character_id', $this->npc->id)
            ->first();
        $participant->update(['last_diary_message_id' => null]);

        $service = app(DiaryWriterService::class);
        $result = $service->writeForScene($this->scene->fresh());

        $this->assertSame(0, $result['created']);
        $this->assertSame(0, CharacterDiaryEntry::query()->where('character_id', $this->npc->id)->count());
        $participant->refresh();
        $this->assertNull($participant->last_diary_message_id);
    }

    public function test_write_for_scene_writes_despite_high_write_threshold_when_invoked_directly(): void
    {
        config(['diary.write_threshold' => 100]);
        $this->fakeChat()->push('Запись L0 без префикса метаданных.');
        $this->fakeEmbeddings();

        $message = $this->createSceneMessage('Единственное новое сообщение.');

        $service = app(DiaryWriterService::class);
        $result = $service->writeForScene($this->scene->fresh());

        $this->assertSame(1, $result['created']);
        $entry = CharacterDiaryEntry::query()
            ->where('character_id', $this->npc->id)
            ->where('level', 0)
            ->first();
        $this->assertNotNull($entry);
        $this->assertSame((int) $message->id, (int) $entry->from_message_id);
        $this->assertSame((int) $message->id, (int) $entry->to_message_id);

        $participant = SceneParticipant::query()
            ->where('scene_id', $this->scene->id)
            ->where('character_id', $this->npc->id)
            ->first();
        $this->assertSame((int) $message->id, (int) $participant->last_diary_message_id);
    }

    public function test_write_for_scene_updates_last_diary_message_id_to_last_processed_message(): void
    {
        $this->fakeChat()->push('Сводка по нескольким репликам.');
        $this->fakeEmbeddings();

        $first = $this->createSceneMessage('Первая реплика.');
        $second = $this->createSceneMessage('Вторая реплика.');
        $third = $this->createSceneMessage('Третья реплика.');

        $service = app(DiaryWriterService::class);
        $result = $service->writeForScene($this->scene->fresh());

        $this->assertSame(1, $result['created']);
        $this->assertSame(3, $result['processed_messages']);

        $entry = CharacterDiaryEntry::query()
            ->where('character_id', $this->npc->id)
            ->sole();
        $this->assertSame((int) $first->id, (int) $entry->from_message_id);
        $this->assertSame((int) $third->id, (int) $entry->to_message_id);

        $participant = SceneParticipant::query()
            ->where('scene_id', $this->scene->id)
            ->where('character_id', $this->npc->id)
            ->first();
        $this->assertSame((int) $third->id, (int) $participant->last_diary_message_id);
    }

    public function test_write_for_scene_creates_l0_for_each_npc_when_llm_succeeds(): void
    {
        $secondNpcResponse = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Второй NPC',
                'character_type' => 'npc',
                'chronicle_id' => $this->chronicle->id,
            ]);
        $secondNpcResponse->assertStatus(201);
        $secondNpc = Character::query()->findOrFail($secondNpcResponse->json('character.id'));

        SceneParticipant::factory()->create([
            'scene_id' => $this->scene->id,
            'chronicle_id' => $this->chronicle->id,
            'character_id' => $secondNpc->id,
            'role' => SceneParticipantRole::Npc,
            'is_current' => true,
            'entered_at' => now(),
        ]);

        $chat = $this->fakeChat();
        $chat->push('Запись первого NPC.');
        $chat->push('Запись второго NPC.');
        $this->fakeEmbeddings();

        $this->createSceneMessage('Общая реплика для обоих.');

        $service = app(DiaryWriterService::class);
        $result = $service->writeForScene($this->scene->fresh());

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, $result['npcs']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(2, CharacterDiaryEntry::query()->where('level', 0)->count());
    }

    public function test_write_for_scene_continues_after_llm_failure_for_one_npc(): void
    {
        $secondNpcResponse = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Третий NPC',
                'character_type' => 'npc',
                'chronicle_id' => $this->chronicle->id,
            ]);
        $secondNpcResponse->assertStatus(201);
        $secondNpc = Character::query()->findOrFail($secondNpcResponse->json('character.id'));

        SceneParticipant::factory()->create([
            'scene_id' => $this->scene->id,
            'chronicle_id' => $this->chronicle->id,
            'character_id' => $secondNpc->id,
            'role' => SceneParticipantRole::Npc,
            'is_current' => true,
            'entered_at' => now(),
        ]);

        $chat = $this->fakeChat();
        $chat->push('Успех для одного NPC.');
        $this->fakeEmbeddings();

        $this->createSceneMessage('Реплика при нехватке ответов LLM.');

        $service = app(DiaryWriterService::class);
        $result = $service->writeForScene($this->scene->fresh());

        $this->assertSame(1, $result['created']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('NPC '.$secondNpc->id, $result['errors'][0]);
    }

    public function test_write_final_for_npc_creates_entry_up_to_left_message_id(): void
    {
        $this->fakeChat()->push('Финальная запись при выходе NPC.');
        $this->fakeEmbeddings();

        $m1 = $this->createSceneMessage('До курсора.');
        $m2 = $this->createSceneMessage('После курсора.');
        $m3 = $this->createSceneMessage('В пределах left.');
        $this->createSceneMessage('После left — не должно попасть.');

        $participant = SceneParticipant::query()
            ->where('scene_id', $this->scene->id)
            ->where('character_id', $this->npc->id)
            ->first();
        $participant->update([
            'is_current' => false,
            'left_at' => now(),
            'last_diary_message_id' => $m1->id,
            'left_message_id' => $m3->id,
        ]);

        $service = app(DiaryWriterService::class);
        $created = $service->writeFinalForNpc($this->scene->fresh(), $this->npc);

        $this->assertTrue($created);
        $entry = CharacterDiaryEntry::query()
            ->where('character_id', $this->npc->id)
            ->where('level', 0)
            ->sole();
        $this->assertSame((int) $m2->id, (int) $entry->from_message_id);
        $this->assertSame((int) $m3->id, (int) $entry->to_message_id);
    }

    public function test_write_final_for_npc_returns_false_for_player_character(): void
    {
        $this->fakeChat()->push('Не должен вызываться для игрока.');
        $this->fakeEmbeddings();

        $playerUser = User::factory()->create();

        $playerResponse = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Игрок-персонаж',
                'character_type' => 'player',
                'chronicle_id' => $this->chronicle->id,
                'user_id' => $playerUser->id,
            ]);
        $playerResponse->assertStatus(201);
        $player = Character::query()->findOrFail($playerResponse->json('character.id'));

        $message = $this->createSceneMessage('Реплика игрока.');

        SceneParticipant::factory()->create([
            'scene_id' => $this->scene->id,
            'chronicle_id' => $this->chronicle->id,
            'character_id' => $player->id,
            'role' => SceneParticipantRole::Player,
            'is_current' => false,
            'entered_at' => now()->subHour(),
            'left_at' => now(),
            'left_message_id' => $message->id,
        ]);

        $service = app(DiaryWriterService::class);
        $created = $service->writeFinalForNpc($this->scene->fresh(), $player);

        $this->assertFalse($created);
        $this->assertSame(
            0,
            CharacterDiaryEntry::query()->where('character_id', $player->id)->count(),
        );
    }

    public function test_summarize_skips_when_fewer_than_two_l0_entries(): void
    {
        $this->fakeChat()->push('Не должен вызываться при одной L0.');
        $this->fakeEmbeddings();

        $this->createDiaryEntry('Единственная L0', level: 0);

        $service = app(DiarySummarizerService::class);
        $result = $service->summarizeScene($this->scene->fresh());

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(
            0,
            CharacterDiaryEntry::query()->where('character_id', $this->npc->id)->where('level', 1)->count(),
        );
    }

    public function test_summarize_creates_l1_when_at_least_two_l0_entries_exist(): void
    {
        $this->fakeChat()->push('L1-сводка сцены без префикса.');
        $this->fakeEmbeddings();

        $this->createDiaryEntry('Первая L0', level: 0, fromMessageId: 1, toMessageId: 5);
        $this->createDiaryEntry('Вторая L0', level: 0, fromMessageId: 6, toMessageId: 10);

        $service = app(DiarySummarizerService::class);
        $result = $service->summarizeScene($this->scene->fresh());

        $this->assertSame(1, $result['created']);
        $l1 = CharacterDiaryEntry::query()
            ->where('character_id', $this->npc->id)
            ->where('level', 1)
            ->sole();
        $this->assertSame(1, (int) $l1->from_message_id);
        $this->assertSame(10, (int) $l1->to_message_id);
    }

    public function test_summarize_second_run_does_not_create_duplicate_l1(): void
    {
        $chat = $this->fakeChat();
        $chat->push('Первая L1-сводка.');
        $this->fakeEmbeddings();

        $this->createDiaryEntry('L0 один', level: 0, fromMessageId: 1, toMessageId: 3);
        $this->createDiaryEntry('L0 два', level: 0, fromMessageId: 4, toMessageId: 6);

        $service = app(DiarySummarizerService::class);
        $first = $service->summarizeScene($this->scene->fresh());
        $this->assertSame(1, $first['created']);

        $second = $service->summarizeScene($this->scene->fresh());
        $this->assertSame(0, $second['created']);
        $this->assertSame(1, $second['skipped']);
        $this->assertSame(
            1,
            CharacterDiaryEntry::query()->where('character_id', $this->npc->id)->where('level', 1)->count(),
        );
    }

    public function test_summarize_scene_job_writes_l0_tail_then_creates_l1(): void
    {
        $chat = $this->fakeChat();
        $chat->push('Хвост L0 после курсора.');
        $chat->push('L1 после закрытия сцены.');
        $this->fakeEmbeddings();

        $this->createDiaryEntry('Старая L0', level: 0, fromMessageId: 1, toMessageId: 2);
        $this->createDiaryEntry('Ещё L0', level: 0, fromMessageId: 3, toMessageId: 4);

        $tailMessage = $this->createSceneMessage('Сообщение после курсора.');

        $participant = SceneParticipant::query()
            ->where('scene_id', $this->scene->id)
            ->where('character_id', $this->npc->id)
            ->first();
        $participant->update(['last_diary_message_id' => 4]);

        $job = new SummarizeSceneDiaryJob($this->scene->id);
        $job->handle(app(DiaryWriterService::class), app(DiarySummarizerService::class));

        $l0Count = CharacterDiaryEntry::query()
            ->where('character_id', $this->npc->id)
            ->where('level', 0)
            ->count();
        $this->assertSame(3, $l0Count);

        $tail = CharacterDiaryEntry::query()
            ->where('character_id', $this->npc->id)
            ->where('level', 0)
            ->where('to_message_id', $tailMessage->id)
            ->first();
        $this->assertNotNull($tail);

        $this->assertSame(
            1,
            CharacterDiaryEntry::query()->where('character_id', $this->npc->id)->where('level', 1)->count(),
        );
    }
}
