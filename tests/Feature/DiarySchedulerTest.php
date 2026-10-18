<?php

namespace Tests\Feature;

use App\Diary\DiaryScheduler;
use App\Enums\ChronicleRole;
use App\Enums\SceneParticipantRole;
use App\Jobs\SummarizeSceneDiaryJob;
use App\Jobs\WriteDiaryJob;
use App\Jobs\WriteFinalDiaryJob;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesDiaryEntries;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class DiarySchedulerTest extends TestCase
{
    use CreatesDiaryEntries;
    use JoinsChronicle;

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
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);

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
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Абрахам',
                'character_type' => 'npc',
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

    public function test_write_diary_job_dispatched_once_when_threshold_reached(): void
    {
        config(['diary.write_threshold' => 2]);
        Queue::fake();

        foreach (range(1, 3) as $i) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                    'scene_id' => $this->scene->id,
                    'body' => "Сообщение номер {$i}",
                ])
                ->assertStatus(201);
        }

        Queue::assertPushed(WriteDiaryJob::class, function (WriteDiaryJob $job): bool {
            return $job->sceneId === $this->scene->id;
        });
    }

    public function test_write_diary_job_not_dispatched_below_threshold(): void
    {
        config(['diary.write_threshold' => 3]);
        Queue::fake();

        foreach (range(1, 2) as $i) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                    'scene_id' => $this->scene->id,
                    'body' => "Сообщение номер {$i}",
                ])
                ->assertStatus(201);
        }

        Queue::assertNotPushed(WriteDiaryJob::class);
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

    public function test_write_final_diary_job_dispatched_when_participant_leaves_after_messages(): void
    {
        Queue::fake();

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'scene_id' => $this->scene->id,
                'body' => 'Реплика NPC',
            ])
            ->assertStatus(201);

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$this->scene->id}/participants/{$this->npc->id}")
            ->assertStatus(200);

        Queue::assertPushed(WriteFinalDiaryJob::class, function (WriteFinalDiaryJob $job): bool {
            return $job->sceneId === $this->scene->id && $job->characterId === $this->npc->id;
        });
    }

    public function test_write_final_diary_job_not_dispatched_when_participant_leaves_empty_scene(): void
    {
        Queue::fake();

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/scenes/{$this->scene->id}/participants/{$this->npc->id}")
            ->assertStatus(200);

        Queue::assertNotPushed(WriteFinalDiaryJob::class);
    }

    public function test_message_edit_marks_overlapping_diary_entries_stale(): void
    {
        $messageResponse = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'scene_id' => $this->scene->id,
                'body' => 'Целевое сообщение',
            ]);
        $messageResponse->assertStatus(201);
        $messageId = (int) $messageResponse->json('message.id');

        $entry = $this->createDiaryEntry(
            'Запись',
            level: 0,
            fromMessageId: $messageId,
            toMessageId: $messageId,
        );

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/messages/{$messageId}/ooc", ['is_ooc' => true])
            ->assertStatus(200);

        $this->assertTrue($entry->refresh()->is_stale);
    }

    public function test_scheduler_dispatches_jobs_with_after_commit_flag(): void
    {
        Queue::fake();

        app(DiaryScheduler::class)->scheduleSummary($this->scene);

        Queue::assertPushed(SummarizeSceneDiaryJob::class, function (SummarizeSceneDiaryJob $job): bool {
            return $job->sceneId === $this->scene->id && $job->afterCommit === true;
        });
    }

    public function test_after_commit_callbacks_skip_on_rollback(): void
    {
        $ran = false;

        DB::beginTransaction();
        DB::afterCommit(function () use (&$ran): void {
            $ran = true;
        });
        DB::rollBack();

        $this->assertFalse($ran);

        DB::beginTransaction();
        DB::afterCommit(function () use (&$ran): void {
            $ran = true;
        });
        DB::commit();

        $this->assertTrue($ran);
    }
}
