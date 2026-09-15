<?php

namespace Tests\Feature;

use App\Enums\ExtractionRunStatus;
use App\Enums\WorldEntityType;
use App\Enums\WorldEventStatus;
use App\Jobs\RunSceneExtractionJob;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\ExtractionRun;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEvent;
use App\Models\WorldEventSource;
use App\Models\WorldRelation;
use App\World\WorldEntityService;
use App\World\WorldRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExtractorSceneTest extends TestCase
{
    use RefreshDatabase;

    public function test_scene_extract_returns_events_and_relations_without_writing_canon(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $entities = $this->app->make(WorldEntityService::class);
        $victoria = $entities->create($chronicle, WorldEntityType::Character, 'Виктория');
        $camarilla = $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $message = Message::factory()->create([
            'scene_id' => $scene->id,
            'body' => 'Виктория вступила в Камарилью после драки.',
        ]);

        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [
                ['source' => 'Виктория', 'target' => 'Камарилья', 'key' => 'member_of'],
            ],
            'events' => [
                [
                    'title' => 'Драка в Элизиуме',
                    'summary' => 'Перестрелка у барной стойки.',
                    'participants' => [['name' => 'Виктория', 'role' => 'actor']],
                    'source_message_ids' => [$message->id, 999999],
                ],
            ],
            'memories' => [
                ['character' => 'Виктория', 'text' => 'Она запомнит это.', 'node_type' => 'event'],
            ],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        Sanctum::actingAs($storyteller);

        $eventsBefore = WorldEvent::query()->count();
        $relationsBefore = WorldRelation::query()->count();

        $response = $this->postJson('/api/extract', [
            'scene_id' => $scene->id,
        ])->assertCreated();

        $run = ExtractionRun::query()->findOrFail($response->json('extraction_run_id'));
        $this->assertSame('scene', $run->source_type->value);
        $this->assertSame($scene->id, $run->source_id);
        $this->assertCount(1, $run->candidates['events']);
        $this->assertSame('pending', $run->candidates['events'][0]['status']);
        $this->assertSame($victoria->id, $run->candidates['events'][0]['participants'][0]['matched_entity_id']);
        $this->assertSame([$message->id], $run->candidates['events'][0]['source_message_ids']);
        $this->assertCount(1, $run->candidates['relations']);
        $this->assertSame('unsupported_for_source', $run->candidates['discarded'][0]['reason']);
        $this->assertSame(WorldEvent::query()->count(), $eventsBefore);
        $this->assertSame(WorldRelation::query()->count(), $relationsBefore);
    }

    public function test_accept_event_creates_canonical_world_event_with_sources(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $entities = $this->app->make(WorldEntityService::class);
        $victoria = $entities->create($chronicle, WorldEntityType::Character, 'Виктория');
        $message = Message::factory()->create([
            'scene_id' => $scene->id,
            'body' => 'Кровь на паркете.',
        ]);

        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [],
            'events' => [
                [
                    'title' => 'Драка в Элизиуме',
                    'summary' => 'Перестрелка у барной стойки.',
                    'participants' => [['name' => 'Виктория']],
                    'source_message_ids' => [$message->id],
                ],
            ],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', ['scene_id' => $scene->id])
            ->assertCreated()
            ->json('extraction_run_id');

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'event',
        ])->assertOk()
            ->assertJsonPath('run.candidates.events.0.status', 'accepted');

        $event = WorldEvent::query()->where('title', 'Драка в Элизиуме')->firstOrFail();
        $this->assertSame(WorldEventStatus::Canonical, $event->status);
        $this->assertSame($scene->id, $event->scene_id);
        $this->assertDatabaseHas('world_event_participants', [
            'event_id' => $event->id,
            'entity_id' => $victoria->id,
        ]);
        $this->assertTrue(
            WorldEventSource::query()
                ->where('event_id', $event->id)
                ->where('scene_id', $scene->id)
                ->exists(),
        );
        $this->assertTrue(
            WorldEventSource::query()
                ->where('event_id', $event->id)
                ->where('message_id', $message->id)
                ->exists(),
        );
    }

    public function test_accept_relation_after_accepted_event_endpoint_resolves(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $entities = $this->app->make(WorldEntityService::class);
        $entities->create($chronicle, WorldEntityType::Character, 'Виктория');
        Message::factory()->create(['scene_id' => $scene->id, 'body' => 'Событие произошло.']);

        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [
                ['source' => 'Виктория', 'target' => 'Драка в Элизиуме', 'key' => 'participated_in'],
            ],
            'events' => [
                [
                    'title' => 'Драка в Элизиуме',
                    'summary' => 'Коротко.',
                    'participants' => [],
                    'source_message_ids' => [],
                ],
            ],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', ['scene_id' => $scene->id])
            ->assertCreated()
            ->json('extraction_run_id');

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'event',
        ])->assertOk();

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'relation',
        ])->assertOk()
            ->assertJsonPath('run.candidates.relations.0.status', 'accepted');

        $this->assertDatabaseCount('world_relations', 1);
    }

    public function test_accept_relation_merges_active_duplicate_on_scene(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $entities = $this->app->make(WorldEntityService::class);
        $victoria = $entities->create($chronicle, WorldEntityType::Character, 'Виктория');
        $camarilla = $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $type = \App\Models\WorldRelationType::query()->where('key', 'member_of')->firstOrFail();
        $this->app->make(WorldRelationService::class)->relate($victoria, $camarilla, $type);
        Message::factory()->create(['scene_id' => $scene->id, 'body' => 'Виктория в Камарилье.']);

        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [
                ['source' => 'Виктория', 'target' => 'Камарилья', 'key' => 'member_of'],
            ],
            'events' => [],
            'memories' => [],
        ]);

        Sanctum::actingAs(User::factory()->storyteller()->create());

        $runId = $this->postJson('/api/extract', ['scene_id' => $scene->id])
            ->assertCreated()
            ->json('extraction_run_id');

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'relation',
        ])->assertOk()
            ->assertJsonPath('run.candidates.relations.0.status', 'merged');

        $this->assertDatabaseCount('world_relations', 1);
    }

    public function test_multiple_source_ids_in_body_return_422(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $this->postJson('/api/extract', [
            'lore_entry_id' => 1,
            'scene_id' => 1,
        ])->assertUnprocessable();

        $this->postJson('/api/extract', [
            'character_id' => 1,
            'scene_id' => 1,
        ])->assertUnprocessable();
    }

    public function test_scene_from_other_chronicle_returns_404(): void
    {
        $otherChronicle = Chronicle::factory()->create();
        $otherSession = GameSession::factory()->create(['chronicle_id' => $otherChronicle->id]);
        $otherScene = Scene::factory()->active()->create(['game_session_id' => $otherSession->id]);
        Message::factory()->create(['scene_id' => $otherScene->id, 'body' => 'Текст.']);

        Sanctum::actingAs(User::factory()->storyteller()->create());

        $this->postJson('/api/extract', [
            'scene_id' => $otherScene->id,
        ])->assertNotFound();
    }

    public function test_empty_scene_returns_422(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        Message::query()->where('scene_id', $scene->id)->delete();

        Sanctum::actingAs(User::factory()->storyteller()->create());

        $this->postJson('/api/extract', [
            'scene_id' => $scene->id,
        ])->assertUnprocessable();
    }

    public function test_player_cannot_extract_scene(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/extract', [
            'scene_id' => 1,
        ])->assertForbidden();
    }

    public function test_close_scene_enqueues_tail_without_calling_ollama(): void
    {
        Http::fake();
        Queue::fake();

        $scene = Scene::query()->active()->firstOrFail();
        Message::factory()->create(['scene_id' => $scene->id, 'body' => 'Текст.']);

        Sanctum::actingAs(User::factory()->storyteller()->create());

        $this->patchJson("/api/scenes/{$scene->id}/close")->assertOk();

        Http::assertNothingSent();
        Queue::assertPushed(RunSceneExtractionJob::class);
    }

    public function test_scene_windows_split_45_messages_into_two_ranges(): void
    {
        config(['extractor.scene_message_limit' => 30]);

        $scene = Scene::query()->active()->firstOrFail();
        Message::query()->where('scene_id', $scene->id)->delete();
        $scene->update(['last_extracted_to_message_id' => null]);
        $storyteller = User::factory()->storyteller()->create();

        $messages = Message::factory()->count(45)->create([
            'scene_id' => $scene->id,
            'user_id' => $storyteller->id,
        ]);

        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        Sanctum::actingAs($storyteller);

        $firstRunId = $this->postJson('/api/extract', ['scene_id' => $scene->id])
            ->assertCreated()
            ->json('extraction_run_id');

        $firstRun = ExtractionRun::query()->findOrFail($firstRunId);
        $this->assertSame($messages[0]->id, $firstRun->from_message_id);
        $this->assertSame($messages[29]->id, $firstRun->to_message_id);
        $this->assertSame(ExtractionRunStatus::Reviewed, $firstRun->status);

        $secondRunId = $this->postJson('/api/extract', ['scene_id' => $scene->id])
            ->assertCreated()
            ->json('extraction_run_id');

        $secondRun = ExtractionRun::query()->findOrFail($secondRunId);
        $this->assertSame($messages[30]->id, $secondRun->from_message_id);
        $this->assertSame($messages[44]->id, $secondRun->to_message_id);
    }

    public function test_auto_job_creates_run_without_blocking_message_post(): void
    {
        config(['extractor.scene_message_limit' => 2]);
        Queue::fake();

        $scene = Scene::query()->active()->firstOrFail();
        Message::query()->where('scene_id', $scene->id)->delete();
        $scene->update(['last_extracted_to_message_id' => null]);
        $storyteller = User::factory()->storyteller()->create();
        Sanctum::actingAs($storyteller);

        Message::factory()->create(['scene_id' => $scene->id, 'user_id' => $storyteller->id, 'body' => 'Один.']);
        $this->postJson('/api/messages', [
            'scene_id' => $scene->id,
            'body' => 'Два.',
        ])->assertCreated();

        Queue::assertPushed(RunSceneExtractionJob::class);
        Http::fake();
        $this->assertDatabaseCount('extraction_runs', 0);
    }

    public function test_failed_scene_window_stays_in_inbox_and_blocks_auto_dispatch(): void
    {
        config(['extractor.scene_message_limit' => 2]);

        $scene = Scene::query()->active()->firstOrFail();
        Message::query()->where('scene_id', $scene->id)->delete();
        $scene->update(['last_extracted_to_message_id' => null]);
        $storyteller = User::factory()->storyteller()->create();
        Sanctum::actingAs($storyteller);

        $first = Message::factory()->create(['scene_id' => $scene->id, 'body' => 'Один.']);
        $second = Message::factory()->create(['scene_id' => $scene->id, 'body' => 'Два.']);

        $allowParse = false;
        $parsedPayload = [
            'mentions' => [
                ['name' => 'Элизиум', 'kind' => 'location'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ];

        Http::fake([
            config('ollama.url').'/api/chat' => function () use (&$allowParse, $parsedPayload) {
                if (! $allowParse) {
                    return Http::response([
                        'message' => ['content' => 'это не json'],
                    ]);
                }

                return Http::response([
                    'message' => [
                        'content' => json_encode($parsedPayload, JSON_UNESCAPED_UNICODE),
                    ],
                ]);
            },
        ]);

        $this->postJson('/api/extract', ['scene_id' => $scene->id])->assertStatus(502);

        $failed = ExtractionRun::query()->firstOrFail();
        $this->assertSame(ExtractionRunStatus::Failed, $failed->status);
        $this->assertSame($first->id, $failed->from_message_id);
        $this->assertSame($second->id, $failed->to_message_id);
        $this->assertNull($scene->fresh()->last_extracted_to_message_id);

        $inbox = $this->getJson('/api/extract/inbox')->assertOk()->json();
        $this->assertSame(1, $inbox['count']);
        $this->assertSame($failed->id, $inbox['runs'][0]['id']);
        $this->assertSame('failed', $inbox['runs'][0]['status']);

        Queue::fake();
        $this->postJson('/api/messages', [
            'scene_id' => $scene->id,
            'body' => 'Три.',
        ])->assertCreated();
        Queue::assertNotPushed(RunSceneExtractionJob::class);

        $allowParse = true;

        $newRunId = $this->postJson("/api/extract/{$failed->id}/reparse")
            ->assertCreated()
            ->json('extraction_run_id');

        $this->assertSame(ExtractionRunStatus::Superseded, $failed->fresh()->status);
        $this->assertSame($second->id, $scene->fresh()->last_extracted_to_message_id);
        $this->assertSame(ExtractionRunStatus::NeedsReview, ExtractionRun::query()->findOrFail($newRunId)->status);
    }

    public function test_reparse_supersedes_old_run_and_does_not_accept_old_pending(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        Message::factory()->create(['scene_id' => $scene->id, 'body' => 'Текст.']);

        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Элизиум', 'kind' => 'location'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        Sanctum::actingAs($storyteller);

        $oldRunId = $this->postJson('/api/extract', ['scene_id' => $scene->id])
            ->assertCreated()
            ->json('extraction_run_id');

        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Новый Элизиум', 'kind' => 'location'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $newRunId = $this->postJson("/api/extract/{$oldRunId}/reparse")
            ->assertCreated()
            ->json('extraction_run_id');

        $this->assertNotSame($oldRunId, $newRunId);
        $this->assertSame(ExtractionRunStatus::Superseded, ExtractionRun::query()->findOrFail($oldRunId)->status);

        $this->postJson("/api/extract/{$oldRunId}/candidates/0/accept", [
            'candidate_type' => 'mention',
        ])->assertUnprocessable();
    }

    public function test_player_cannot_access_extract_inbox(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/extract/inbox')->assertForbidden();
    }

    public function test_copilot_drafts_do_not_write_world_graph_or_extraction_runs(): void
    {
        $this->fakeOllamaCopilotDrafts(['Один.', 'Два.', 'Три.']);

        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $npcEntity = $this->app->make(WorldEntityService::class)->create(
            $chronicle,
            WorldEntityType::Character,
            'Виктория',
        );
        $npc = Character::query()->findOrFail($npcEntity->id);

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/copilot/drafts', [
            'character_id' => $npc->id,
            'prompt' => 'Ответить.',
            'scene_id' => $scene->id,
        ])->assertOk();

        $this->assertDatabaseCount('extraction_runs', 0);
        $this->assertDatabaseCount('world_events', 0);
        $this->assertDatabaseCount('world_relations', 0);
    }

    public function test_storyteller_can_create_world_event_via_http(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $entities = $this->app->make(WorldEntityService::class);
        $character = $entities->create($chronicle, WorldEntityType::Character, 'Виктория');
        $message = Message::factory()->create(['scene_id' => $scene->id, 'body' => 'Источник.']);

        Sanctum::actingAs(User::factory()->storyteller()->create());

        $response = $this->postJson('/api/world/events', [
            'title' => 'Саботаж',
            'description' => 'Кто-то нарушил тишину.',
            'scene_id' => $scene->id,
            'event_type' => 'political',
            'importance' => 3,
        ])->assertCreated();

        $eventId = $response->json('event.id');

        $this->postJson("/api/world/events/{$eventId}/participants", [
            'entity_id' => $character->id,
            'role' => 'actor',
        ])->assertCreated();

        $this->postJson("/api/world/events/{$eventId}/sources", [
            'scene_id' => $scene->id,
            'message_id' => $message->id,
        ])->assertCreated();

        $this->assertDatabaseHas('world_events', ['id' => $eventId, 'title' => 'Саботаж']);
        $this->assertDatabaseHas('world_event_participants', [
            'event_id' => $eventId,
            'entity_id' => $character->id,
        ]);
        $this->assertDatabaseHas('world_event_sources', [
            'event_id' => $eventId,
            'scene_id' => $scene->id,
            'message_id' => $message->id,
        ]);
    }

    public function test_player_cannot_create_world_event_via_http(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/world/events', [
            'title' => 'Саботаж',
        ])->assertForbidden();
    }

    public function test_memory_accept_on_scene_run_returns_422(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        Message::factory()->create(['scene_id' => $scene->id, 'body' => 'Текст.']);

        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [],
            'events' => [],
            'memories' => [
                ['text' => 'Память.', 'node_type' => 'event'],
            ],
        ]);

        Sanctum::actingAs(User::factory()->storyteller()->create());

        $runId = $this->postJson('/api/extract', ['scene_id' => $scene->id])
            ->assertCreated()
            ->json('extraction_run_id');

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'memory',
        ])->assertUnprocessable();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function fakeOllamaExtraction(array $payload): void
    {
        Http::fake([
            config('ollama.url').'/api/chat' => function (Request $request) use ($payload) {
                return Http::response([
                    'message' => [
                        'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    ],
                ]);
            },
        ]);
    }

    /**
     * @param  list<string>  $drafts
     */
    private function fakeOllamaCopilotDrafts(array $drafts): void
    {
        $payload = json_encode(['drafts' => $drafts], JSON_UNESCAPED_UNICODE);

        Http::fake(function (Request $request) use ($payload) {
            $predict = (int) ($request['options']['num_predict'] ?? 3000);
            if ($predict <= 512) {
                return Http::response([
                    'message' => [
                        'content' => json_encode(['topics' => ['тест']], JSON_UNESCAPED_UNICODE),
                    ],
                ]);
            }

            return Http::response([
                'message' => ['content' => $payload],
            ]);
        });
    }
}
