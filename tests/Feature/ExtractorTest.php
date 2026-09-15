<?php

namespace Tests\Feature;

use App\Character\CharacterBiographyService;
use App\Enums\FactionType;
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
        $this->assertDatabaseCount('world_relations', 0);
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
            'location_type' => 'site',
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
        config([
            'extractor.max_output_tokens' => 12000,
            'ollama.context_length' => 16384,
        ]);

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
        $lore = $this->createLoreEntry($chronicle, 'Камарилья правит городом.');

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated();

        Http::assertSent(function (Request $request): bool {
            $body = $request->data();
            $options = $body['options'] ?? [];

            return ($body['think'] ?? null) === false
                && isset($options['num_predict'])
                && $options['num_predict'] <= 12000
                && $options['num_predict'] >= 512;
        });
    }

    public function test_done_reason_length_returns_502_without_run_row(): void
    {
        Http::fake([
            config('ollama.url').'/api/chat' => Http::response([
                'done_reason' => 'length',
                'message' => [
                    'content' => '{"mentions":[{"name":"Камарилья","kind":"faction"}],"relations":[],"events":[],"memories":[]}',
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

        $this->assertStringContainsString('EXTRACTOR_MAX_OUTPUT_TOKENS', (string) $response->json('message'));

        $this->assertDatabaseCount('extraction_runs', 0);
    }

    public function test_oversized_slice_returns_422_without_ollama_call(): void
    {
        config([
            'ollama.context_length' => 1000,
            'extractor.max_output_tokens' => 5000,
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, str_repeat('А', 5000));

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertUnprocessable();

        Http::assertNothingSent();
        $this->assertDatabaseCount('extraction_runs', 0);
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
