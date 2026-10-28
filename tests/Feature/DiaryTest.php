<?php

namespace Tests\Feature;

use App\Diary\DiaryEntryEditor;
use App\Diary\DiaryRetrievalService;
use App\Diary\Exceptions\InvalidDiaryEntryInputException;
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
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesDiaryEntries;
use Tests\Support\CreatesSceneMessages;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class DiaryTest extends TestCase
{
    use CreatesDiaryEntries;
    use CreatesSceneMessages;
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

        $this->storyteller = User::factory()->create();

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

    public function test_write_diary_job_dispatched_when_threshold_reached(): void
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

    public function test_write_diary_job_not_dispatched_when_threshold_is_only_ooc_messages(): void
    {
        config(['diary.write_threshold' => 15]);
        Queue::fake();

        foreach (range(1, 15) as $i) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                    'scene_id' => $this->scene->id,
                    'body' => "OOC {$i}",
                    'is_ooc' => true,
                ])
                ->assertStatus(201);
        }

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                'scene_id' => $this->scene->id,
                'body' => 'Единственное IC-сообщение',
            ])
            ->assertStatus(201);

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

    public function test_diary_jobs_use_write_timeout_from_config(): void
    {
        config(['diary.write_timeout' => 123]);

        $this->assertSame(123, (new WriteDiaryJob($this->scene->id))->timeout);
        $this->assertSame(123, (new SummarizeSceneDiaryJob($this->scene->id))->timeout);
        $this->assertSame(123, (new WriteFinalDiaryJob($this->scene->id, $this->npc->id))->timeout);
    }

    public function test_retrieval_returns_latest_entry_when_query_empty(): void
    {
        $this->createDiaryEntry('Первая запись', level: 0);
        $this->createDiaryEntry('Вторая запись', level: 0);
        $third = $this->createDiaryEntry('Третья запись', level: 0);

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve($this->npc, '', limit: 3);

        $this->assertCount(1, $result);
        $this->assertSame((int) $third->id, (int) $result->first()->id);
    }

    public function test_retrieval_returns_multiple_entries_when_pool_has_l0(): void
    {
        $this->createDiaryEntry('Первая запись', level: 0);
        $second = $this->createDiaryEntry('Вторая запись', level: 0);

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve($this->npc, '', limit: 3);

        // Последняя всегда подаётся. При пустом query semantic не запускается,
        // поэтому результат — только последняя.
        $this->assertCount(1, $result);
        $this->assertSame((int) $second->id, (int) $result->first()->id);
    }

    public function test_retrieval_returns_newest_entry_when_l0_and_l1_in_pool(): void
    {
        $this->createDiaryEntry('L0 запись', level: 0, sceneId: $this->scene->id);
        $l1 = $this->createDiaryEntry('L1 запись', level: 1, sceneId: $this->scene->id);

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve($this->npc, '', limit: 3);

        // L0 и L1 в одном пуле; при пустом query — только запись с максимальным id.
        $this->assertCount(1, $result);
        $this->assertSame((int) $l1->id, (int) $result->first()->id);
    }

    public function test_retrieval_filters_entries_fully_covered_by_recent_window(): void
    {
        // L0 [1..30] начался до recent → остаётся.
        // L0 [31..60] начался внутри recent → отфильтрован целиком.
        $kept = $this->createDiaryEntry(
            'Ранний L0', level: 0,
            fromMessageId: 1, toMessageId: 30,
        );
        $this->createDiaryEntry(
            'L0 внутри recent', level: 0,
            fromMessageId: 31, toMessageId: 60,
        );

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve(
            $this->npc, '', limit: 3,
            recentFirstMessageId: 31,
        );

        $this->assertCount(1, $result);
        $this->assertSame((int) $kept->id, (int) $result->first()->id);
    }

    public function test_retrieval_keeps_entries_partially_covered_by_recent_window(): void
    {
        // L0 [1..30] перехлёстывается с recent [10..24]:
        // сообщения 1–9 уникальны для L0, 10–24 дублируются, 25–30 уникальны снова.
        // Фильтр по from (1 < 10) → L0 остаётся целиком.
        $partial = $this->createDiaryEntry(
            'Частично пересекается', level: 0,
            fromMessageId: 1, toMessageId: 30,
        );

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve(
            $this->npc, '', limit: 3,
            recentFirstMessageId: 10,
        );

        $this->assertCount(1, $result);
        $this->assertSame((int) $partial->id, (int) $result->first()->id);
    }

    public function test_retrieval_returns_empty_when_all_entries_are_within_recent(): void
    {
        // L0 [10..25] полностью внутри recent [10..25] → пул пуст,
        // diary-секция должна быть omitted на уровне DiaryProvider.
        $this->createDiaryEntry(
            'Полностью покрыт recent', level: 0,
            fromMessageId: 10, toMessageId: 25,
        );

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve(
            $this->npc, '', limit: 3,
            recentFirstMessageId: 10,
        );

        $this->assertCount(0, $result);
    }

    public function test_retrieval_keeps_current_scene_entry_even_when_covered_by_recent(): void
    {
        // L0 [10..25] целиком внутри recent [10..25], но это ТЕКУЩАЯ сцена —
        // ослабленный фильтр оставляет её как «голос NPC прямо сейчас».
        $entry = $this->createDiaryEntry(
            'Голос текущей сцены', level: 0,
            sceneId: $this->scene->id,
            fromMessageId: 10, toMessageId: 25,
        );

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve(
            $this->npc, '', limit: 3,
            recentFirstMessageId: 10,
            currentSceneId: $this->scene->id,
        );

        $this->assertCount(1, $result);
        $this->assertSame((int) $entry->id, (int) $result->first()->id);
    }

    public function test_retrieval_still_filters_entry_covered_by_recent_when_not_current_scene(): void
    {
        // Та же L0 [10..25], но «текущей» считается ДРУГАЯ сцена —
        // фильтр работает как раньше, запись отсекается.
        $this->createDiaryEntry(
            'Запись не текущей сцены', level: 0,
            sceneId: $this->scene->id,
            fromMessageId: 10, toMessageId: 25,
        );

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve(
            $this->npc, '', limit: 3,
            recentFirstMessageId: 10,
            currentSceneId: 99999,
        );

        $this->assertCount(0, $result);
    }

    public function test_blend_score_boosts_important_entries(): void
    {
        $service = app(DiaryRetrievalService::class);

        // sim=0.6 imp=0.9 → 0.6 × 0.97 = 0.582
        $boosted = DiaryRetrievalService::blendScore(0.60, 0.9);
        // sim=0.58 imp=null → 0.58
        $plain = DiaryRetrievalService::blendScore(0.58, null);

        $this->assertGreaterThan($plain, $boosted);
    }

    public function test_blend_score_null_importance_is_identity(): void
    {
        $this->assertSame(0.62, DiaryRetrievalService::blendScore(0.62, null));
    }

    public function test_retrieval_semantic_branch_includes_similar_entry_when_query_non_empty(): void
    {
        $embeddings = $this->fakeEmbeddings();

        $oldest = $this->createDiaryEntry(
            'уникальный якорь альфа',
            embeddingSourceText: 'уникальный якорь альфа',
        );
        $this->createDiaryEntry(
            'другой текст бета',
            embeddingSourceText: 'другой текст бета',
        );
        $latest = $this->createDiaryEntry(
            'третий текст гамма',
            embeddingSourceText: 'третий текст гамма',
        );

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve($this->npc, 'уникальный якорь альфа', limit: 3);

        $ids = $result->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertContains((int) $oldest->id, $ids);
        $this->assertContains((int) $latest->id, $ids);
        $this->assertGreaterThan(1, count($ids));
        $this->assertNotEmpty(array_filter(
            $embeddings->calls(),
            fn (array $call): bool => $call['type'] === 'embed' && ($call['text'] ?? '') === 'уникальный якорь альфа',
        ));
    }

    public function test_retrieval_semantic_branch_respects_min_similarity_cutoff(): void
    {
        $this->fakeEmbeddings();

        $this->createDiaryEntry('запись один', embeddingSourceText: 'запись один');
        $this->createDiaryEntry('запись два', embeddingSourceText: 'запись два');
        $latest = $this->createDiaryEntry('запись три', embeddingSourceText: 'запись три');

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve($this->npc, 'совсем другой запрос без пересечений', limit: 3);

        $this->assertCount(1, $result);
        $this->assertSame((int) $latest->id, (int) $result->first()->id);
    }

    public function test_retrieval_semantic_prefers_higher_blend_score_with_importance(): void
    {
        $this->fakeEmbeddings();

        $highImportance = $this->createDiaryEntry(
            'тема важности',
            embeddingSourceText: 'тема важности',
            importance: 0.95,
        );
        $this->createDiaryEntry(
            'тема важности слегка иначе',
            embeddingSourceText: 'тема важности слегка иначе',
            importance: 0.1,
        );
        $this->createDiaryEntry(
            'последняя запись',
            embeddingSourceText: 'последняя запись',
        );

        $service = app(DiaryRetrievalService::class);
        $result = $service->retrieve($this->npc, 'тема важности', limit: 3);

        $ids = $result->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $this->assertContains((int) $highImportance->id, $ids);
    }

    public function test_patch_diary_entry_with_whitespace_only_returns_422(): void
    {
        $entry = $this->createDiaryEntry('Исходный текст');

        $this->expectException(InvalidDiaryEntryInputException::class);
        $this->expectExceptionMessage('Entry cannot be empty.');

        app(DiaryEntryEditor::class)->update($entry, '   ');
    }

    public function test_patch_diary_entry_updates_text_and_embedding(): void
    {
        $embeddings = $this->fakeEmbeddings();
        $entry = $this->createDiaryEntry('Исходный текст');

        $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/diary-entries/{$entry->id}", ['entry' => 'Новый текст записи'])
            ->assertOk()
            ->assertJsonPath('entry.entry', 'Новый текст записи');

        $this->assertNotEmpty(array_filter(
            $embeddings->calls(),
            fn (array $call): bool => $call['type'] === 'embed' && ($call['text'] ?? '') === 'Новый текст записи',
        ));
    }

    public function test_regenerate_diary_entry_fails_without_source(): void
    {
        $entry = $this->createDiaryEntry('Текст', level: 0, fromMessageId: 999, toMessageId: 999);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/diary-entries/{$entry->id}/regenerate")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Не удалось пересоздать запись. Проверьте источник.');
    }

    public function test_regenerate_l0_diary_entry_via_http(): void
    {
        $this->fakeChat()->push('Новый текст L0 после регенерации.');
        $this->fakeEmbeddings();

        $message = $this->createSceneMessage('Реплика для регенерации.');
        $entry = $this->createDiaryEntry(
            'Старый текст',
            level: 0,
            fromMessageId: (int) $message->id,
            toMessageId: (int) $message->id,
        );

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/diary-entries/{$entry->id}/regenerate")
            ->assertOk()
            ->assertJsonPath('entry.entry', 'Новый текст L0 после регенерации.');
    }

    public function test_regenerate_l1_diary_entry_via_http(): void
    {
        $this->fakeChat()->push('Обновлённая L1-сводка.');
        $this->fakeEmbeddings();

        $this->createDiaryEntry('L0 один', level: 0, fromMessageId: 1, toMessageId: 5);
        $this->createDiaryEntry('L0 два', level: 0, fromMessageId: 6, toMessageId: 10);
        $l1 = $this->createDiaryEntry('Старая L1', level: 1, fromMessageId: 1, toMessageId: 10);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/diary-entries/{$l1->id}/regenerate")
            ->assertOk()
            ->assertJsonPath('entry.entry', 'Обновлённая L1-сводка.');
    }
}
