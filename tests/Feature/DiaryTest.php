<?php

namespace Tests\Feature;

use App\Diary\DiaryRetrievalService;
use App\Enums\CharacterType;
use App\Enums\GameSessionStatus;
use App\Enums\SceneParticipantRole;
use App\Enums\SceneStatus;
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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DiaryTest extends TestCase
{
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

        $this->chronicle = Chronicle::query()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $this->storyteller->id,
        ]);

        $this->gameSession = GameSession::query()->create([
            'chronicle_id' => $this->chronicle->id,
            'title' => 'Тестовая сессия',
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

    public function test_write_diary_job_dispatched_when_threshold_reached(): void
    {
        config(['diary.write_threshold' => 2]);
        Queue::fake();

        foreach (range(1, 3) as $i) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson('/api/messages', [
                    'scene_id' => $this->scene->id,
                    'body' => "Сообщение номер {$i}",
                ])
                ->assertStatus(201);
        }

        Queue::assertPushed(WriteDiaryJob::class, function (WriteDiaryJob $job): bool {
            return $job->sceneId === $this->scene->id;
        });
    }

    public function test_summarize_scene_diary_job_dispatched_on_close(): void
    {
        Queue::fake();

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$this->scene->id}/close")
            ->assertStatus(200);

        Queue::assertPushed(SummarizeSceneDiaryJob::class, function (SummarizeSceneDiaryJob $job): bool {
            return $job->sceneId === $this->scene->id;
        });
    }

    public function test_retrieval_returns_latest_entry_when_query_empty(): void
    {
        $first = $this->createDiaryEntry('Первая запись', level: 0);
        $second = $this->createDiaryEntry('Вторая запись', level: 0);
        $third = $this->createDiaryEntry('Третья запись', level: 0);

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve($this->npc, '', limit: 3);

        $this->assertCount(1, $result);
        $this->assertSame((int) $third->id, (int) $result->first()->id);
    }

    public function test_retrieval_returns_multiple_entries_when_pool_has_l0(): void
    {
        $first = $this->createDiaryEntry('Первая запись', level: 0);
        $second = $this->createDiaryEntry('Вторая запись', level: 0);

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve($this->npc, '', limit: 3);

        // Последняя всегда подаётся. При пустом query semantic не запускается,
        // поэтому результат — только последняя.
        $this->assertCount(1, $result);
        $this->assertSame((int) $second->id, (int) $result->first()->id);
    }

    public function test_retrieval_prefers_l1_over_l0_for_same_scene(): void
    {
        $this->createDiaryEntry('L0 запись', level: 0, sceneId: $this->scene->id);
        $l1 = $this->createDiaryEntry('L1 запись', level: 1, sceneId: $this->scene->id);

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve($this->npc, '', limit: 3);

        $this->assertCount(1, $result);
        $this->assertSame((int) $l1->id, (int) $result->first()->id);
    }

    private function createDiaryEntry(string $text, int $level, ?int $sceneId = null): CharacterDiaryEntry
    {
        $vector = '['.implode(',', array_fill(0, 1024, '0.1')).']';

        $id = DB::table('character_diary_entries')->insertGetId([
            'character_id' => $this->npc->id,
            'chronicle_id' => $this->chronicle->id,
            'scene_id' => $sceneId,
            'level' => $level,
            'entry' => $text,
            'embedding' => DB::raw("'{$vector}'::vector"),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return CharacterDiaryEntry::query()->findOrFail($id);
    }
}