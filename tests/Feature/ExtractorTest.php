<?php

namespace Tests\Feature;

use App\Character\CharacterBiographyService;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Enums\WorldEntityType;
use App\Lore\LoreEntryService;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\ExtractionRun;
use App\Models\User;
use App\Models\WorldEntity;
use App\Models\WorldEvent;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesExtractorResponses;
use Tests\TestCase;

class ExtractorTest extends TestCase
{
    use FakesExtractorResponses;
    use RefreshDatabase;

    public function test_known_name_matches_entity_without_writing_graph(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Камарилья', 'kind' => 'faction'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $faction = $this->app->make(WorldEntityService::class)->create(
            $chronicle,
            WorldEntityType::Faction,
            'Камарилья',
            aliases: ['Камарильи'],
        );
        $lore = $this->createLoreEntry($chronicle, 'Камарилья правит городом.');

        $entityCountBefore = WorldEntity::query()->count();
        $relationCountBefore = WorldRelation::query()->count();

        Sanctum::actingAs($storyteller);

        $response = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated();

        $this->assertSame(WorldEntity::query()->count(), $entityCountBefore);
        $this->assertSame(WorldRelation::query()->count(), $relationCountBefore);

        $run = ExtractionRun::query()->findOrFail($response->json('extraction_run_id'));
        $this->assertSame($faction->id, $run->candidates['mentions'][0]['matched_entity_id']);
        $this->assertSame('pending', $run->candidates['mentions'][0]['status']);
        $this->assertArrayNotHasKey('candidate_type', $run->candidates['mentions'][0]);

        Http::assertSentCount(1);
    }

    public function test_unknown_name_becomes_new_entity_candidate_without_create(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Незнакомец', 'kind' => 'character'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Незнакомец появился в переулке.');
        $entityCountBefore = WorldEntity::query()->count();

        Sanctum::actingAs($storyteller);

        $response = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated();

        $this->assertSame(WorldEntity::query()->count(), $entityCountBefore);

        $run = ExtractionRun::query()->findOrFail($response->json('extraction_run_id'));
        $mention = $run->candidates['mentions'][0];
        $this->assertSame('new_entity', $mention['candidate_type']);
        $this->assertSame('pending', $mention['status']);
        $this->assertArrayNotHasKey('matched_entity_id', $mention);
    }

    public function test_unknown_relation_key_is_discarded(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [
                ['source' => 'Виктория', 'target' => 'Камарилья', 'key' => 'not_a_real_key'],
            ],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $entities = $this->app->make(WorldEntityService::class);
        $entities->create($chronicle, WorldEntityType::Character, 'Виктория');
        $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $lore = $this->createLoreEntry($chronicle, 'Виктория служит Камарилье.');

        Sanctum::actingAs($storyteller);

        $response = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated();

        $run = ExtractionRun::query()->findOrFail($response->json('extraction_run_id'));
        $this->assertSame([], $run->candidates['relations']);
        $this->assertSame('invalid_key', $run->candidates['discarded'][0]['reason']);
        $this->assertSame('not_a_real_key', $run->candidates['discarded'][0]['key']);
        $this->assertSame('reviewed', $run->status->value);
        $this->assertDatabaseCount('world_relations', 0);
    }

    public function test_duplicate_mention_names_are_deduped_in_candidates(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Бруха', 'kind' => 'faction'],
                ['name' => 'бруха', 'kind' => 'faction'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Бруха бунтует.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $run = ExtractionRun::query()->findOrFail($runId);
        $this->assertCount(1, $run->candidates['mentions']);
        $this->assertSame('Бруха', $run->candidates['mentions'][0]['name']);
    }

    public function test_relation_endpoints_missing_from_mentions_are_synthesized_once(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Традиции Каина', 'kind' => 'concept'],
                ['name' => 'Бруха', 'kind' => 'faction'],
            ],
            'relations' => [
                ['source' => 'Камарилья', 'target' => 'Традиции Каина', 'key' => 'created'],
                ['source' => 'Камарилья', 'target' => 'Бруха', 'key' => 'member_of'],
            ],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Камарилья и традиции.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $run = ExtractionRun::query()->findOrFail($runId);
        $mentionNames = array_column($run->candidates['mentions'], 'name');
        $this->assertSame(['Традиции Каина', 'Бруха', 'Камарилья'], $mentionNames);
        $this->assertTrue($run->candidates['mentions'][2]['synthesized_from_relations'] ?? false);
        $this->assertCount(2, $run->candidates['relations']);
    }

    public function test_lore_reextract_supersedes_prior_needs_review_run(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Камарилья', 'kind' => 'faction'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Камарилья.');

        Sanctum::actingAs($storyteller);

        $firstId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $secondId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->assertNotSame($firstId, $secondId);
        $this->assertSame('superseded', ExtractionRun::query()->findOrFail($firstId)->status->value);
        $this->assertSame('needs_review', ExtractionRun::query()->findOrFail($secondId)->status->value);

        $this->getJson('/api/extract/inbox?count_only=true')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_run_without_pending_candidates_is_marked_reviewed(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [],
            'events' => [
                ['title' => 'Битва', 'summary' => 'Случилось', 'participants' => ['Виктория']],
            ],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Текст.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $run = ExtractionRun::query()->findOrFail($runId);
        $this->assertSame('reviewed', $run->status->value);
        $this->assertNotEmpty($run->candidates['discarded']);
    }

    public function test_accepting_last_pending_candidate_marks_run_reviewed(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Элизиум', 'kind' => 'location'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Элизиум — клуб.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'mention',
        ])->assertOk()
            ->assertJsonPath('run.status', 'reviewed');
    }

    public function test_broken_json_returns_502_without_run_row(): void
    {
        $logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $event) use (&$logged): void {
            if ($event->message === 'extractor.parse_failed') {
                $logged[] = $event;
            }
        });

        Http::fake([
            config('ollama.url').'/api/chat' => Http::response([
                'message' => ['content' => 'это не json'],
            ]),
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Текст статьи.');

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertStatus(502);

        $this->assertDatabaseCount('extraction_runs', 0);
        $this->assertCount(1, $logged);
        $this->assertSame('warning', $logged[0]->level);
        $this->assertArrayNotHasKey('body', $logged[0]->context);
        $this->assertSame('no json object', $logged[0]->context['json_error'] ?? null);
        $this->assertSame(strlen('это не json'), $logged[0]->context['bytes'] ?? null);
        $path = $logged[0]->context['file'] ?? null;
        $this->assertIsString($path);
        $this->assertStringContainsString('extractor-fails', $path);
        $this->assertFileExists($path);
        $this->assertSame('это не json', file_get_contents($path));
        unlink($path);
    }

    public function test_qwen_thinking_prefix_is_stripped_before_parse(): void
    {
        Http::fake([
            config('ollama.url').'/api/chat' => Http::response([
                'message' => [
                    'content' => "**Thinking...**\n\nNeed JSON.\n\n**...done thinking.**\n\n"
                        .'{"mentions":[{"name":"Камарилья","kind":"faction"}],"relations":[],"events":[],"memories":[]}',
                ],
            ]),
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Камарилья правит городом.');

        Sanctum::actingAs($storyteller);

        $response = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated();

        $run = ExtractionRun::query()->findOrFail($response->json('extraction_run_id'));
        $this->assertSame('Камарилья', $run->candidates['mentions'][0]['name']);
    }

    public function test_disabled_driver_returns_503_without_ollama_call(): void
    {
        config(['extractor.driver' => 'none']);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Текст статьи.');

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertStatus(503);

        Http::assertNothingSent();
        $this->assertDatabaseCount('extraction_runs', 0);
    }

    public function test_player_cannot_run_extraction(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/extract', [
            'lore_entry_id' => 1,
        ])->assertForbidden();
    }

    public function test_biography_extract_returns_memory_candidates_and_discards_events(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Камарилья', 'kind' => 'faction'],
            ],
            'relations' => [],
            'events' => [
                ['title' => 'Битва', 'summary' => 'Случилось', 'participants' => ['Виктория']],
            ],
            'memories' => [
                ['text' => 'Виктория помнит тайную встречу.', 'node_type' => 'event'],
            ],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $npc = $this->createNpcWithBiography($chronicle, 'Виктория помнит тайную встречу в Элизиуме.');

        Sanctum::actingAs($storyteller);

        $response = $this->postJson('/api/extract', [
            'character_id' => $npc->id,
        ])->assertCreated();

        $run = ExtractionRun::query()->findOrFail($response->json('extraction_run_id'));
        $this->assertSame('biography', $run->source_type->value);
        $this->assertSame($npc->id, $run->source_id);
        $this->assertCount(1, $run->candidates['memories']);
        $this->assertSame('pending', $run->candidates['memories'][0]['status']);
        $this->assertSame([], $run->candidates['mentions']);
        $this->assertSame([], $run->candidates['relations']);
        $this->assertSame('unsupported_for_source', $run->candidates['discarded'][0]['reason']);
        $this->assertDatabaseCount('character_memory_nodes', 0);
        $this->assertDatabaseCount('world_events', 0);
    }

    public function test_accept_memory_remembers_for_character(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [],
            'events' => [],
            'memories' => [
                ['text' => 'Виктория помнит тайную встречу.', 'node_type' => 'event'],
            ],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $npc = $this->createNpcWithBiography($chronicle, 'Виктория помнит тайную встречу.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'character_id' => $npc->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->assertDatabaseCount('character_memory_nodes', 0);

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'memory',
        ])->assertOk()
            ->assertJsonPath('run.candidates.memories.0.status', 'accepted');

        $this->assertDatabaseCount('character_memory_nodes', 1);
        $this->assertDatabaseHas('character_memory_nodes', [
            'character_id' => $npc->id,
            'node_text' => 'Виктория помнит тайную встречу.',
            'node_type' => 'event',
        ]);
        $this->assertDatabaseCount('world_events', 0);
    }

    public function test_cannot_provide_lore_and_character_together(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create());

        $this->postJson('/api/extract', [
            'lore_entry_id' => 1,
            'character_id' => 1,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('lore_entry_id');
    }

    public function test_accept_new_entity_creates_directory_entity(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Элизиум', 'kind' => 'location'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Элизиум — клуб.');
        $countBefore = WorldEntity::query()->count();

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $response = $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'mention',
        ])->assertOk()
            ->assertJsonPath('run.candidates.mentions.0.status', 'accepted');

        $createdId = $response->json('run.candidates.mentions.0.created_entity_id');
        $this->assertIsInt($createdId);
        $this->assertSame($countBefore + 1, WorldEntity::query()->count());
        $this->assertDatabaseHas('locations', [
            'id' => $createdId,
            'entity_type' => WorldEntityType::Location->value,
        ]);
    }

    public function test_accept_relation_creates_edge_for_known_entities(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [
                ['source' => 'Виктория', 'target' => 'Камарилья', 'key' => 'member_of'],
            ],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $entities = $this->app->make(WorldEntityService::class);
        $entities->create($chronicle, WorldEntityType::Character, 'Виктория');
        $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $lore = $this->createLoreEntry($chronicle, 'Виктория состоит в Камарилье.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->assertDatabaseCount('world_relations', 0);

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'relation',
        ])->assertOk()
            ->assertJsonPath('run.candidates.relations.0.status', 'accepted');

        $this->assertDatabaseCount('world_relations', 1);
    }

    public function test_extract_status_reports_disabled_driver(): void
    {
        config(['extractor.driver' => 'none']);

        Sanctum::actingAs(User::factory()->storyteller()->create());

        $this->getJson('/api/extract/status')
            ->assertOk()
            ->assertJsonPath('enabled', false);
    }

    public function test_ollama_request_disables_thinking_and_clamps_num_predict(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Камарилья', 'kind' => 'faction'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, str_repeat('К', 10000));

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $options = $body['options'] ?? [];

            return array_key_exists('think', $body)
                && $body['think'] === false
                && ($options['num_predict'] ?? 0) <= 7128
                && ($options['num_predict'] ?? 0) >= 512;
        });
    }

    public function test_done_reason_length_returns_502_without_run_row(): void
    {
        Http::fake([
            config('ollama.url').'/api/chat' => Http::response([
                'done_reason' => 'length',
                'message' => [
                    'content' => '{"mentions":[{"name":"Камарилья","kind":"faction"}],"relations":[]}',
                ],
            ]),
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Камарилья правит городом.');

        Sanctum::actingAs($storyteller);

        $response = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertStatus(502);

        $this->assertStringContainsString('EXTRACTOR_LORE_OUTPUT_TOKENS', (string) $response->json('message'));

        $this->assertDatabaseCount('extraction_runs', 0);
    }

    public function test_oversized_slice_returns_422_without_ollama_call(): void
    {
        config([
            'ollama.context_length' => 1000,
            'extractor.profiles.lore.output_tokens' => 7128,
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, str_repeat('А', 10000));

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertDatabaseCount('extraction_runs', 0);
    }

    public function test_lore_long_article_is_split_into_ten_k_char_windows(): void
    {
        config(['extractor.profiles.lore.article_max_chars' => 10000]);

        $this->fakeOllamaExtraction([
            'mentions' => [['name' => 'Камарилья', 'kind' => 'faction']],
            'relations' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $text = str_repeat('А', 15000);
        $lore = $this->createLoreEntry($chronicle, $text);

        Sanctum::actingAs($storyteller);

        $firstId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $firstRun = ExtractionRun::query()->findOrFail($firstId);
        $this->assertSame(0, $firstRun->from_char_offset);
        $this->assertSame(10000, $firstRun->to_char_offset);

        Http::assertSent(function (Request $request): bool {
            $content = $request->data()['messages'][1]['content'] ?? '';

            return mb_strlen($this->sliceTextFromPrompt($content)) <= 10000;
        });

        $firstRun->update(['status' => 'reviewed']);

        $secondId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $secondRun = ExtractionRun::query()->findOrFail($secondId);
        $this->assertSame(10000, $secondRun->from_char_offset);
        $this->assertSame(15000, $secondRun->to_char_offset);
        $this->assertSame('reviewed', $firstRun->fresh()->status->value);
    }

    public function test_lore_reextract_supersedes_only_same_char_window(): void
    {
        config(['extractor.profiles.lore.article_max_chars' => 10000]);

        $this->fakeOllamaExtraction([
            'mentions' => [['name' => 'Камарилья', 'kind' => 'faction']],
            'relations' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, str_repeat('Б', 15000));

        Sanctum::actingAs($storyteller);

        $windowOneId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        ExtractionRun::query()->findOrFail($windowOneId)->update(['status' => 'reviewed']);

        $windowTwoId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->fakeOllamaExtraction([
            'mentions' => [['name' => 'Шабаш', 'kind' => 'faction']],
            'relations' => [],
        ]);

        $windowTwoRetryId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->assertSame('superseded', ExtractionRun::query()->findOrFail($windowTwoId)->status->value);
        $this->assertSame('reviewed', ExtractionRun::query()->findOrFail($windowOneId)->status->value);
    }

    public function test_lore_reparse_from_scratch_after_reviewed_run(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [['name' => 'Камарилья', 'kind' => 'faction']],
            'relations' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Камарилья правит городом.');

        Sanctum::actingAs($storyteller);

        $firstId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        ExtractionRun::query()->findOrFail($firstId)->update(['status' => 'reviewed']);

        $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertUnprocessable();

        $secondId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
            'reparse' => true,
        ])->assertCreated()->json('extraction_run_id');

        $this->assertNotSame($firstId, $secondId);
        $this->assertSame('superseded', ExtractionRun::query()->findOrFail($firstId)->status->value);
        $this->assertSame(0, ExtractionRun::query()->findOrFail($secondId)->from_char_offset);
        $this->assertSame('reparse', ExtractionRun::query()->findOrFail($secondId)->trigger->value);
    }

    public function test_lore_reparse_endpoint_reruns_char_window(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [['name' => 'Шабаш', 'kind' => 'faction']],
            'relations' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, str_repeat('А', 15000));

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        ExtractionRun::query()->findOrFail($runId)->update(['status' => 'reviewed']);

        $replacementId = $this->postJson("/api/extract/{$runId}/reparse")
            ->assertCreated()
            ->json('extraction_run_id');

        $this->assertNotSame($runId, $replacementId);
        $this->assertSame('superseded', ExtractionRun::query()->findOrFail($runId)->status->value);
        $this->assertSame(0, ExtractionRun::query()->findOrFail($replacementId)->from_char_offset);
        $this->assertSame(10000, ExtractionRun::query()->findOrFail($replacementId)->to_char_offset);
    }

    public function test_reparse_flag_is_only_valid_for_lore(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $npc = $this->createNpcWithBiography(Chronicle::query()->firstOrFail(), 'Био.');

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/extract', [
            'character_id' => $npc->id,
            'reparse' => true,
        ])->assertUnprocessable();
    }

    public function test_lore_num_predict_is_capped_at_seven_thousand_one_hundred_twenty_eight(): void
    {
        config([
            'extractor.profiles.lore.article_max_chars' => 10000,
            'ollama.context_length' => 16384,
        ]);

        $this->fakeOllamaExtraction([
            'mentions' => [['name' => 'Камарилья', 'kind' => 'faction']],
            'relations' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, str_repeat('В', 10000));

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated();

        Http::assertSent(function (Request $request): bool {
            $options = $request->data()['options'] ?? [];

            return ($options['num_predict'] ?? 0) <= 7128;
        });
    }

    public function test_lore_prompt_uses_graph_extract_v2_rules(): void
    {
        $relationKeys = \App\Models\WorldRelationType::query()
            ->where('enabled', true)
            ->orderBy('key')
            ->pluck('key')
            ->all();

        $messages = $this->app->make(\App\Extractor\ExtractionPromptBuilder::class)->build(
            'Отступники Бруха в Мехико.',
            [],
            $relationKeys,
        );

        $system = $messages[0]['content'];
        $user = $messages[1]['content'];

        $this->assertStringContainsString('You extract a chronicle lore graph.', $system);
        $this->assertStringContainsString('Do not summarize lists.', $user);
        $this->assertStringContainsString('Отступники Бруха', $system);
        $this->assertStringNotContainsString('textbook facts unrelated to this chronicle', $system);
        $this->assertStringNotContainsString('"events"', $system);
    }

    public function test_accept_part_of_creates_single_graph_edge_for_code_articles(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [
                ['source' => 'Статья I', 'target' => 'Кодекс Милана', 'key' => 'part_of'],
            ],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $entities = $this->app->make(WorldEntityService::class);
        $entities->create($chronicle, WorldEntityType::Concept, 'Кодекс Милана');
        $entities->create($chronicle, WorldEntityType::Concept, 'Статья I');
        $lore = $this->createLoreEntry($chronicle, 'Статья I входит в кодекс.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->assertDatabaseCount('world_relations', 0);

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'relation',
        ])->assertOk()
            ->assertJsonPath('run.candidates.relations.0.key', 'part_of')
            ->assertJsonPath('run.candidates.relations.0.status', 'accepted');

        $this->assertDatabaseCount('world_relations', 1);
        $partOfId = WorldRelationType::query()->where('key', 'part_of')->value('id');
        $this->assertTrue(WorldRelation::query()->where('relation_type_id', $partOfId)->active()->exists());
    }

    public function test_contains_relation_is_normalized_to_part_of_in_candidates(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Кодекс Милана', 'kind' => 'concept'],
                ['name' => 'Статья I', 'kind' => 'concept'],
            ],
            'relations' => [
                ['source' => 'Кодекс Милана', 'target' => 'Статья I', 'key' => 'contains'],
            ],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $entities = $this->app->make(WorldEntityService::class);
        $entities->create($chronicle, WorldEntityType::Concept, 'Кодекс Милана');
        $entities->create($chronicle, WorldEntityType::Concept, 'Статья I');
        $lore = $this->createLoreEntry($chronicle, 'Статья I входит в кодекс.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $run = ExtractionRun::query()->findOrFail($runId);
        $this->assertSame('part_of', $run->candidates['relations'][0]['key']);
        $this->assertSame('Статья I', $run->candidates['relations'][0]['source']);
        $this->assertSame('Кодекс Милана', $run->candidates['relations'][0]['target']);
    }

    public function test_extract_status_reports_lore_limits_and_window_preview(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, str_repeat('Г', 15000));

        Sanctum::actingAs($storyteller);

        $this->getJson('/api/extract/status?lore_entry_id='.$lore->id)
            ->assertOk()
            ->assertJsonPath('enabled', true)
            ->assertJsonPath('lore.article_max_chars', 10000)
            ->assertJsonPath('lore_window.from_char_offset', 0)
            ->assertJsonPath('lore_window.to_char_offset', 10000)
            ->assertJsonPath('lore_window.total_chars', 15000)
            ->assertJsonPath('lore_window.window_count', 2)
            ->assertJsonPath('lore_window.can_extract', true);
    }

    public function test_lore_extract_status_reports_can_extract_false_when_exhausted(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [['name' => 'Камарилья', 'kind' => 'faction']],
            'relations' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Камарилья.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        ExtractionRun::query()->findOrFail($runId)->update(['status' => 'reviewed']);

        $this->getJson('/api/extract/status?lore_entry_id='.$lore->id)
            ->assertOk()
            ->assertJsonPath('lore_window.can_extract', false);
    }

    private function sliceTextFromPrompt(string $prompt): string
    {
        if (! preg_match('/## Source text\n\n(.+?)\n\n## Known entities/su', $prompt, $matches)) {
            return '';
        }

        return $matches[1];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createNpcWithBiography(Chronicle $chronicle, string $fullText): Character
    {
        $npcEntity = $this->app->make(WorldEntityService::class)->create(
            $chronicle,
            WorldEntityType::Character,
            'Виктория',
        );
        $npc = Character::query()->findOrFail($npcEntity->id);

        $this->app->make(CharacterBiographyService::class)->publish(
            $npc,
            [
                'summary' => 'Кратко.',
                'full_text' => $fullText,
            ],
            'Fixture for biography extractor tests.',
        );

        return $npc;
    }
}
