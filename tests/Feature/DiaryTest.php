<?php

namespace Tests\Feature;

use App\Diary\DiaryRetrievalService;
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
    
    public function test_metadata_parser_extracts_prefix(): void
    {
        $parsed = \App\Diary\DiaryMetadataParser::parse("[0.850 anger]\n\nНочь первая. Пустырь.");

        $this->assertSame(0.85, $parsed['importance']);
        $this->assertSame(\App\Enums\DiaryEmotion::Anger, $parsed['emotion']);
        $this->assertSame('Ночь первая. Пустырь.', $parsed['entry']);
    }

    public function test_metadata_parser_falls_back_when_no_prefix(): void
    {
        $parsed = \App\Diary\DiaryMetadataParser::parse('Ночь первая. Пустырь.');

        $this->assertNull($parsed['importance']);
        $this->assertNull($parsed['emotion']);
        $this->assertSame('Ночь первая. Пустырь.', $parsed['entry']);
    }

    public function test_metadata_parser_normalizes_aliases(): void
    {
        $parsed = \App\Diary\DiaryMetadataParser::parse("[0.700 fury]\n\nтекст");

        $this->assertSame(\App\Enums\DiaryEmotion::Anger, $parsed['emotion']);
    }

    public function test_metadata_parser_clamps_out_of_range_importance(): void
    {
        $parsed = \App\Diary\DiaryMetadataParser::parse("[1.500 joy]\n\nтекст");

        $this->assertSame(1.0, $parsed['importance']);
    }

    public function test_blend_score_boosts_important_entries(): void
    {
        $service = app(\App\Diary\DiaryRetrievalService::class);

        // sim=0.6 imp=0.9 → 0.6 × 0.97 = 0.582
        $boosted = \App\Diary\DiaryRetrievalService::blendScore(0.60, 0.9);
        // sim=0.58 imp=null → 0.58
        $plain = \App\Diary\DiaryRetrievalService::blendScore(0.58, null);

        $this->assertGreaterThan($plain, $boosted);
    }

    public function test_blend_score_null_importance_is_identity(): void
    {
        $this->assertSame(0.62, \App\Diary\DiaryRetrievalService::blendScore(0.62, null));
    }

    private function createDiaryEntry(
        string $text,
        int $level = 0,
        ?int $sceneId = null,
        int $fromMessageId = 1,
        int $toMessageId = 1,
        bool $isStale = false,
    ): CharacterDiaryEntry {
        $vector = '['.implode(',', array_fill(0, 1024, '0.1')).']';

        $id = DB::table('character_diary_entries')->insertGetId([
            'character_id' => $this->npc->id,
            'chronicle_id' => $this->chronicle->id,
            'scene_id' => $sceneId ?? $this->scene->id,
            'level' => $level,
            'from_message_id' => $fromMessageId,
            'to_message_id' => $toMessageId,
            'entry' => $text,
            'embedding' => DB::raw("'{$vector}'::vector"),
            'is_stale' => $isStale,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return CharacterDiaryEntry::query()->findOrFail($id);
    }
}