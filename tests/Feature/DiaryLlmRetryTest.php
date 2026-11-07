<?php

namespace Tests\Feature;

use App\Diary\DiaryWriterService;
use App\Enums\ChronicleRole;
use App\Enums\SceneParticipantRole;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Support\Facades\Log;
use Tests\Support\CreatesDiaryEntries;
use Tests\Support\CreatesSceneMessages;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class DiaryLlmRetryTest extends TestCase
{
    use CreatesDiaryEntries;
    use CreatesSceneMessages;
    use JoinsChronicle;

    private User $storyteller;

    private Chronicle $chronicle;

    private Scene $scene;

    private Character $npc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->chronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);

        $gameSession = GameSession::factory()->active()->create([
            'chronicle_id' => $this->chronicle->id,
            'created_by' => $this->storyteller->id,
        ]);

        $this->scene = Scene::factory()->active()->create([
            'game_session_id' => $gameSession->id,
            'position' => 1,
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

    public function test_write_concatenates_continuation_when_first_response_hit_length_limit(): void
    {
        $firstPart = 'Запись дневника обрывается на полусл';
        $secondPart = 'ове и продолжается здесь.';

        $chat = $this->fakeChat();
        $chat->push([
            'content' => $firstPart,
            'finishReason' => 'length',
        ]);
        $chat->push([
            'content' => $secondPart,
            'finishReason' => 'stop',
        ]);
        $this->fakeEmbeddings();

        $this->createSceneMessage('Реплика для записи.');

        $service = app(DiaryWriterService::class);
        $result = $service->writeForScene($this->scene->fresh());

        $this->assertSame(1, $result->created);
        $entry = CharacterDiaryEntry::query()
            ->where('character_id', $this->npc->id)
            ->where('level', 0)
            ->sole();
        $this->assertSame($firstPart.$secondPart, $entry->entry);
        $this->assertFalse($entry->is_truncated);

        $this->assertCount(2, $chat->requests());
        $retryMessages = $chat->requests()[1]['messages'];
        $continuation = $retryMessages[array_key_last($retryMessages)];
        $this->assertSame('user', $continuation['role']);
        $this->assertStringContainsString('Продолжи текст с этого места', $continuation['content']);
        $this->assertStringContainsString(mb_substr($firstPart, -200), $continuation['content']);
    }

    public function test_write_sets_is_truncated_and_logs_when_retry_also_hits_length_limit(): void
    {
        Log::spy();

        $firstPart = 'Первая часть без метаданных.';
        $secondPart = 'Вторая часть тоже обрезана.';

        $this->fakeChat()
            ->push([
                'content' => $firstPart,
                'finishReason' => 'length',
            ])
            ->push([
                'content' => $secondPart,
                'finishReason' => 'length',
            ]);
        $this->fakeEmbeddings();

        $this->createSceneMessage('Реплика для обрезанной записи.');

        $service = app(DiaryWriterService::class);
        $result = $service->writeForScene($this->scene->fresh());

        $this->assertSame(1, $result->created);
        $entry = CharacterDiaryEntry::query()
            ->where('character_id', $this->npc->id)
            ->where('level', 0)
            ->sole();
        $this->assertSame($firstPart.$secondPart, $entry->entry);
        $this->assertTrue($entry->is_truncated);

        Log::shouldHaveReceived('warning')
            ->with('diary.llm.truncated_after_retry', \Mockery::subset([
                'npc_id' => $this->npc->id,
                'scene_id' => $this->scene->id,
            ]))
            ->once();
    }

    public function test_regenerate_l0_clears_is_truncated_when_llm_succeeds(): void
    {
        $this->fakeChat()->push('Полный текст после пересоздания.');
        $this->fakeEmbeddings();

        $message = $this->createSceneMessage('Реплика для регенерации.');
        $entry = $this->createDiaryEntry(
            'Обрезанная запись',
            level: 0,
            fromMessageId: (int) $message->id,
            toMessageId: (int) $message->id,
        );
        $entry->update(['is_truncated' => true]);
        $this->assertTrue($entry->fresh()->is_truncated);

        $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/diary-entries/{$entry->id}/regenerate")
            ->assertOk()
            ->assertJsonPath('entry.is_truncated', false);

        $this->assertFalse($entry->fresh()->is_truncated);
    }
}
