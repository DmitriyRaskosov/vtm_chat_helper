<?php

namespace Tests\Feature;

use App\Enums\SceneParticipantRole;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\CreatesDiaryEntries;
use Tests\Support\CreatesSceneMessages;
use Tests\TestCase;

class DiaryJsonShapeTest extends TestCase
{
    use CreatesDiaryEntries;
    use CreatesSceneMessages;

    private const LIST_ENTRY_KEYS = [
        'id',
        'character_id',
        'scene_id',
        'scene_title',
        'level',
        'from_message_id',
        'to_message_id',
        'is_stale',
        'importance',
        'emotion',
        'preview',
        'word_count',
        'created_at',
    ];

    private const FULL_ENTRY_KEYS = [
        'id',
        'character_id',
        'scene_id',
        'scene_title',
        'level',
        'from_message_id',
        'to_message_id',
        'is_stale',
        'importance',
        'emotion',
        'preview',
        'word_count',
        'created_at',
        'entry',
    ];

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
            'title' => 'Сцена дневника',
        ]);

        $npcResponse = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Дневник-NPC',
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

    public function test_get_character_diary_index_shape(): void
    {
        $entry = $this->createDiaryEntry('Первая запись дневника для списка');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/characters/{$this->npc->id}/diary");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'character_id',
            'entries' => [
                '*' => self::LIST_ENTRY_KEYS,
            ],
        ]);

        $response->assertJsonPath('character_id', (int) $this->npc->id);

        $item = collect($response->json('entries'))->firstWhere('id', (int) $entry->id);
        $this->assertNotNull($item);
        $this->assertSame(self::LIST_ENTRY_KEYS, array_keys($item));
        $this->assertSame((int) $this->npc->id, $item['character_id']);
        $this->assertSame((int) $this->scene->id, $item['scene_id']);
        $this->assertSame('Сцена дневника', $item['scene_title']);
        $this->assertSame('Первая запись дневника для списка', $item['preview']);
        $this->assertSame(5, $item['word_count']);
        $this->assertIsString($item['created_at']);
    }

    public function test_get_diary_entry_show_shape(): void
    {
        $entry = $this->createDiaryEntry('Полный текст записи');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/diary-entries/{$entry->id}");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'entry' => self::FULL_ENTRY_KEYS,
        ]);

        $item = $response->json('entry');
        $this->assertSame(self::FULL_ENTRY_KEYS, array_keys($item));
        $this->assertSame('Полный текст записи', $item['entry']);
        $this->assertSame('Полный текст записи', $item['preview']);
    }

    public function test_patch_diary_entry_response_shape(): void
    {
        $this->fakeEmbeddings();
        $entry = $this->createDiaryEntry('Исходный текст');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->patchJson("/api/diary-entries/{$entry->id}", ['entry' => 'Обновлённый текст']);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'entry' => self::FULL_ENTRY_KEYS,
        ]);

        $item = $response->json('entry');
        $this->assertSame(self::FULL_ENTRY_KEYS, array_keys($item));
        $this->assertSame('Обновлённый текст', $item['entry']);
    }

    public function test_regenerate_diary_entry_response_shape(): void
    {
        $this->fakeChat()->push('Текст после регенерации.');
        $this->fakeEmbeddings();

        $message = $this->createSceneMessage('Реплика для регенерации.');
        $entry = $this->createDiaryEntry(
            'Старый текст',
            level: 0,
            fromMessageId: (int) $message->id,
            toMessageId: (int) $message->id,
        );

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/diary-entries/{$entry->id}/regenerate");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'entry' => self::FULL_ENTRY_KEYS,
        ]);

        $item = $response->json('entry');
        $this->assertSame(self::FULL_ENTRY_KEYS, array_keys($item));
    }

    public function test_copilot_drafts_response_shape(): void
    {
        $this->fakeChat()
            ->push('{"topics":["t1"]}')
            ->push('{"drafts":["alpha","beta","gamma"]}');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/copilot/drafts', [
                'scene_id' => $this->scene->id,
                'character_id' => $this->npc->id,
                'prompt' => 'Синопсис для формы ответа',
            ]);

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'copilot_request_id',
            'drafts',
        ]);
        $this->assertIsInt($response->json('copilot_request_id'));
        $this->assertSame(['alpha', 'beta', 'gamma'], $response->json('drafts'));
    }
}
