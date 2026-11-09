<?php

namespace Tests\Feature;

use App\Combat\CombatService;
use App\Diary\DiarySummarizerService;
use App\Diary\DiaryWriterService;
use App\Enums\ChronicleRole;
use App\Enums\SceneParticipantRole;
use App\Jobs\SummarizeSceneDiaryJob;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use App\Scene\SceneParticipantService;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\Support\CreatesDiaryEntries;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CombatDiaryTest extends TestCase
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

        $this->storyteller = User::factory()->create();

        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Хроника боя и дневника',
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);

        $this->gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);

        $this->scene = Scene::factory()->active()->create([
            'game_session_id' => $this->gameSession->id,
            'position' => 1,
        ]);

        $npcResponse = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Боевой NPC',
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

    public function test_l0_gets_combat_id(): void
    {
        $encounter = app(CombatService::class)->startEncounter($this->scene);
        $vector = '['.implode(',', array_fill(0, 1024, '0.1')).']';

        $id = DB::table('character_diary_entries')->insertGetId([
            'character_id' => $this->npc->id,
            'chronicle_id' => $this->chronicle->id,
            'scene_id' => $this->scene->id,
            'combat_encounter_id' => $encounter->id,
            'level' => 0,
            'from_message_id' => 1,
            'to_message_id' => 2,
            'entry' => 'L0 в бою.',
            'embedding' => DB::raw("'{$vector}'::vector"),
            'is_stale' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame($encounter->id, (int) CharacterDiaryEntry::query()->findOrFail($id)->combat_encounter_id);
    }

    public function test_no_l1_from_single_l0(): void
    {
        $this->fakeChat()->push('Не должен вызываться.');
        $this->fakeEmbeddings();

        $encounter = app(CombatService::class)->startEncounter($this->scene);
        $this->createDiaryEntry('Одна L0 в бою', level: 0, fromMessageId: 1, toMessageId: 2, combatEncounterId: $encounter->id);

        $result = app(DiarySummarizerService::class)->summarizeScene($this->scene, $encounter->id);

        $this->assertSame(0, $result->created);
        $this->assertSame(
            0,
            CharacterDiaryEntry::query()->where('character_id', $this->npc->id)->where('level', 1)->count(),
        );
    }

    public function test_scene_close_summarizes_only_new_l0(): void
    {
        $chat = $this->fakeChat();
        $chat->push('Первая L1.');
        $chat->push('Вторая L1 только из новых L0.');
        $this->fakeEmbeddings();

        $this->createDiaryEntry('Старая L0', level: 0, fromMessageId: 1, toMessageId: 5, combatEncounterId: null);
        $this->createDiaryEntry('Старая L0 2', level: 0, fromMessageId: 6, toMessageId: 10, combatEncounterId: null);

        $first = app(DiarySummarizerService::class)->summarizeScene($this->scene);
        $this->assertSame(1, $first->created);

        $this->createDiaryEntry('Новая L0', level: 0, fromMessageId: 11, toMessageId: 15, combatEncounterId: null);
        $this->createDiaryEntry('Новая L0 2', level: 0, fromMessageId: 16, toMessageId: 20, combatEncounterId: null);

        $second = app(DiarySummarizerService::class)->summarizeScene($this->scene);
        $this->assertSame(1, $second->created);

        $l1Rows = CharacterDiaryEntry::query()
            ->where('character_id', $this->npc->id)
            ->where('level', 1)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $l1Rows);
        $this->assertSame(10, (int) $l1Rows[0]->to_message_id);
        $this->assertSame(20, (int) $l1Rows[1]->to_message_id);
        $this->assertSame(11, (int) $l1Rows[1]->from_message_id);
    }

    public function test_multiple_l1_per_scene_allowed(): void
    {
        $chat = $this->fakeChat();
        $chat->push('L1 до боя.');
        $chat->push('L1 после боя.');
        $this->fakeEmbeddings();

        $this->createDiaryEntry('До боя 1', level: 0, fromMessageId: 1, toMessageId: 3, combatEncounterId: null);
        $this->createDiaryEntry('До боя 2', level: 0, fromMessageId: 4, toMessageId: 6, combatEncounterId: null);

        Queue::fake();
        $encounter = app(CombatService::class)->startEncounter($this->scene);
        Queue::assertPushed(SummarizeSceneDiaryJob::class, fn (SummarizeSceneDiaryJob $job): bool => $job->sceneId === $this->scene->id
            && $job->combatEncounterId === null
            && $job->characterId === null);

        $preCombatL1 = app(DiarySummarizerService::class)->summarizeScene($this->scene);
        $this->assertSame(1, $preCombatL1->created);

        $this->createDiaryEntry('В бою 1', level: 0, fromMessageId: 7, toMessageId: 9, combatEncounterId: $encounter->id);
        $this->createDiaryEntry('В бою 2', level: 0, fromMessageId: 10, toMessageId: 12, combatEncounterId: $encounter->id);

        $finished = app(CombatService::class)->finishEncounter($encounter);
        $this->assertNotNull($finished->ended_at);

        $combatL1 = app(DiarySummarizerService::class)->summarizeScene($this->scene, $encounter->id);
        $this->assertSame(1, $combatL1->created);

        $l1Rows = CharacterDiaryEntry::query()
            ->where('character_id', $this->npc->id)
            ->where('level', 1)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $l1Rows);
        $this->assertNull($l1Rows[0]->combat_encounter_id);
        $this->assertSame($encounter->id, (int) $l1Rows[1]->combat_encounter_id);
        $this->assertSame(6, (int) $l1Rows[0]->to_message_id);
        $this->assertSame(12, (int) $l1Rows[1]->to_message_id);
    }

    public function test_combat_start_summarizes_pre_combat_l0(): void
    {
        $this->fakeChat()->push('L1 из пре-боевых L0.');
        $this->fakeEmbeddings();

        $this->createDiaryEntry('Пре-бой 1', level: 0, fromMessageId: 1, toMessageId: 3);
        $this->createDiaryEntry('Пре-бой 2', level: 0, fromMessageId: 4, toMessageId: 6);

        Queue::fake();
        app(CombatService::class)->startEncounter($this->scene);

        Queue::assertPushed(SummarizeSceneDiaryJob::class, function (SummarizeSceneDiaryJob $job): bool {
            return $job->sceneId === $this->scene->id
                && $job->combatEncounterId === null;
        });
    }

    public function test_combat_finish_summarizes_combat_l0(): void
    {
        $chat = $this->fakeChat();
        $chat->push('Игнор при старте');
        $chat->push('L1 боевых L0.');
        $this->fakeEmbeddings();

        $encounter = app(CombatService::class)->startEncounter($this->scene);

        $this->createDiaryEntry('Бой 1', level: 0, fromMessageId: 7, toMessageId: 8, combatEncounterId: $encounter->id);
        $this->createDiaryEntry('Бой 2', level: 0, fromMessageId: 9, toMessageId: 10, combatEncounterId: $encounter->id);

        Queue::fake([SummarizeSceneDiaryJob::class]);

        app(CombatService::class)->finishEncounter($encounter->refresh());

        Queue::assertPushed(SummarizeSceneDiaryJob::class, fn (SummarizeSceneDiaryJob $job): bool => $job->sceneId === $this->scene->id
            && $job->combatEncounterId === $encounter->id);

        $job = new SummarizeSceneDiaryJob($this->scene->id, $encounter->id);
        $job->handle(app(DiaryWriterService::class), app(DiarySummarizerService::class));

        $l1 = CharacterDiaryEntry::query()
            ->where('character_id', $this->npc->id)
            ->where('level', 1)
            ->where('combat_encounter_id', $encounter->id)
            ->first();

        $this->assertNotNull($l1);
    }

    public function test_participant_leave_during_combat_summarizes_own_l0(): void
    {
        $this->fakeChat()->push('L1 при выходе из боя.');
        $this->fakeEmbeddings();

        $encounter = app(CombatService::class)->startEncounter($this->scene);
        app(CombatService::class)->addParticipant($encounter, $this->npc);

        $this->createDiaryEntry('Бойевой хвост 1', level: 0, fromMessageId: 1, toMessageId: 2, combatEncounterId: $encounter->id);
        $this->createDiaryEntry('Бойевой хвост 2', level: 0, fromMessageId: 3, toMessageId: 4, combatEncounterId: $encounter->id);

        Queue::fake();

        app(SceneParticipantService::class)->leave($this->scene, $this->npc, $this->storyteller);

        Queue::assertPushed(SummarizeSceneDiaryJob::class, fn (SummarizeSceneDiaryJob $job): bool => $job->sceneId === $this->scene->id
            && $job->combatEncounterId === $encounter->id
            && $job->characterId === $this->npc->id);
    }

    public function test_summarize_scene_diary_job_backward_compatible_constructor(): void
    {
        $job = new SummarizeSceneDiaryJob($this->scene->id);

        $this->assertSame($this->scene->id, $job->sceneId);
        $this->assertNull($job->combatEncounterId);
        $this->assertNull($job->characterId);
    }
}
