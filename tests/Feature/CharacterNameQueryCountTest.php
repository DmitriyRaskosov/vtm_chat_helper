<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Support\Facades\DB;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CharacterNameQueryCountTest extends TestCase
{
    use JoinsChronicle;

    /** Зафиксировано до задачи 023 (DB::listen на демо-подобных данных). */
    /** До задачи 023: 5; после 038 (членство рассказчика): 6; после character_states: 7 (+1 eager load state). */
    private const MAX_QUERIES_CHARACTER_SHOW = 7;

    /** До задачи 023: 5; после 038: 6; после 039: 8 (+2 authorize('view', chronicle) → chronicle_members.exists() + CurrentChronicle::get() → find((int) $chronicle)). */
    private const MAX_QUERIES_CHARACTER_INDEX = 8;

    /** До задачи 023: 4; после 039: 6; после 040: 7 (+1 ActiveSceneResolver::findInActiveSession всегда читает CurrentChronicle при явном scene_id). */
    private const MAX_QUERIES_MESSAGES_INDEX = 7;

    /** До задачи 023: 2; после 044 (ScenePolicy::view → chronicle_members + gameSession.chronicle): 5. */
    private const MAX_QUERIES_SCENE_PARTICIPANTS = 5;

    private User $storyteller;

    private Chronicle $chronicle;

    private int $characterId;

    private int $sceneId;

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

        foreach (['NPC 1', 'NPC 2', 'NPC 3', 'NPC 4', 'NPC 5'] as $name) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                    'canonical_name' => $name,
                    'character_type' => 'npc',
                ])
                ->assertStatus(201);
        }

        $this->characterId = (int) $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'characters'), [
                'canonical_name' => 'Герой листа',
                'character_type' => 'npc',
            ])
            ->json('character.id');

        $session = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson($this->chronicleApi($this->chronicle, 'game-sessions'), ['title' => 'Сессия'])
            ->assertStatus(201);

        $this->sceneId = (int) $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/game-sessions/'.$session->json('game_session.id').'/scenes', [
                'title' => 'Сцена',
            ])
            ->json('scene.id');

        for ($i = 1; $i <= 20; $i++) {
            $this->actingAs($this->storyteller, 'sanctum')
                ->postJson($this->chronicleApi($this->chronicle, 'messages'), [
                    'scene_id' => $this->sceneId,
                    'body' => "Сообщение {$i}",
                ])
                ->assertStatus(201);
        }
    }

    public function test_get_character_show_query_count_within_budget(): void
    {
        $response = null;
        $count = $this->countQueriesForRequest(function () use (&$response): void {
            $response = $this->actingAs($this->storyteller, 'sanctum')
                ->getJson("/api/characters/{$this->characterId}");
        });
        $response->assertStatus(200);

        $this->assertLessThanOrEqual(
            self::MAX_QUERIES_CHARACTER_SHOW,
            $count,
            "GET /characters/{id} used {$count} queries (max ".self::MAX_QUERIES_CHARACTER_SHOW.')',
        );
    }

    public function test_get_characters_index_query_count_within_budget(): void
    {
        $response = null;
        $count = $this->countQueriesForRequest(function () use (&$response): void {
            $response = $this->actingAs($this->storyteller, 'sanctum')
                ->getJson($this->chronicleApi($this->chronicle, 'characters'));
        });
        $response->assertStatus(200);

        $this->assertLessThanOrEqual(
            self::MAX_QUERIES_CHARACTER_INDEX,
            $count,
            "GET /characters used {$count} queries (max ".self::MAX_QUERIES_CHARACTER_INDEX.')',
        );
    }

    public function test_get_messages_query_count_within_budget(): void
    {
        $count = $this->countQueriesForRequest(fn () => $this->actingAs($this->storyteller, 'sanctum')
            ->getJson($this->chronicleApi($this->chronicle, 'messages').'?scene_id='.$this->sceneId));

        $this->assertLessThanOrEqual(
            self::MAX_QUERIES_MESSAGES_INDEX,
            $count,
            "GET /messages used {$count} queries (max ".self::MAX_QUERIES_MESSAGES_INDEX.')',
        );
    }

    public function test_get_scene_participants_query_count_within_budget(): void
    {
        $count = $this->countQueriesForRequest(fn () => $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/scenes/{$this->sceneId}/participants"));

        $this->assertLessThanOrEqual(
            self::MAX_QUERIES_SCENE_PARTICIPANTS,
            $count,
            "GET /scenes/{id}/participants used {$count} queries (max ".self::MAX_QUERIES_SCENE_PARTICIPANTS.')',
        );
    }

    private function countQueriesForRequest(callable $request): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });

        $request();

        return $count;
    }
}
