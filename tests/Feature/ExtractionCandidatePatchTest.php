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
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\FakesExtractorResponses;
use Tests\TestCase;

class ExtractionCandidatePatchTest extends TestCase
{
    use FakesExtractorResponses;
    use RefreshDatabase;

    public function test_alias_from_other_chronicle_does_not_match(): void
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
        $primaryChronicle = Chronicle::query()->firstOrFail();
        $otherChronicle = Chronicle::factory()->create();
        $this->app->make(WorldEntityService::class)->create(
            $otherChronicle,
            WorldEntityType::Faction,
            'Камарилья',
        );
        $lore = $this->createLoreEntry($primaryChronicle, 'Камарилья упоминается в тексте.');

        Sanctum::actingAs($storyteller);

        $response = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
            'chronicle_id' => $primaryChronicle->id,
        ])->assertCreated();

        $run = ExtractionRun::query()->findOrFail($response->json('extraction_run_id'));
        $mention = $run->candidates['mentions'][0];
        $this->assertSame('new_entity', $mention['candidate_type']);
        $this->assertArrayNotHasKey('matched_entity_id', $mention);
    }

    public function test_storyteller_can_fetch_run_by_id(): void
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
        $lore = $this->createLoreEntry($chronicle, 'Элизиум — клуб для вампиров.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->getJson("/api/extract/{$runId}")
            ->assertOk()
            ->assertJsonPath('run.id', $runId)
            ->assertJsonPath('run.source_type', 'lore')
            ->assertJsonPath('run.source_id', $lore->id);
    }

    public function test_discard_memory_does_not_remember(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [],
            'events' => [],
            'memories' => [
                ['text' => 'Отброшенное воспоминание.', 'node_type' => 'rumor'],
            ],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $npc = $this->createNpcWithBiography($chronicle, 'Отброшенное воспоминание в био.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'character_id' => $npc->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->postJson("/api/extract/{$runId}/candidates/0/discard", [
            'candidate_type' => 'memory',
        ])->assertOk()
            ->assertJsonPath('run.candidates.memories.0.status', 'discarded');

        $this->assertDatabaseCount('character_memory_nodes', 0);
    }

    public function test_accept_relation_merges_active_duplicate(): void
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
        $victoria = $entities->create($chronicle, WorldEntityType::Character, 'Виктория');
        $camarilla = $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $type = \App\Models\WorldRelationType::query()->where('key', 'member_of')->firstOrFail();
        $this->app->make(\App\World\WorldRelationService::class)->relate($victoria, $camarilla, $type);
        $lore = $this->createLoreEntry($chronicle, 'Виктория в Камарилье.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'relation',
        ])->assertOk()
            ->assertJsonPath('run.candidates.relations.0.status', 'merged');

        $this->assertDatabaseCount('world_relations', 1);
    }

    public function test_accept_relation_requires_resolved_endpoints(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [],
            'relations' => [
                ['source' => 'Виктория', 'target' => 'Новая фракция', 'key' => 'member_of'],
            ],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $this->app->make(WorldEntityService::class)->create($chronicle, WorldEntityType::Character, 'Виктория');
        $lore = $this->createLoreEntry($chronicle, 'Виктория и новая фракция.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'relation',
        ])->assertUnprocessable();

        $this->assertDatabaseCount('world_relations', 0);
    }

    public function test_accept_relation_after_new_entity_mention(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Новая фракция', 'kind' => 'faction'],
            ],
            'relations' => [
                ['source' => 'Виктория', 'target' => 'Новая фракция', 'key' => 'member_of'],
            ],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $this->app->make(WorldEntityService::class)->create($chronicle, WorldEntityType::Character, 'Виктория');
        $lore = $this->createLoreEntry($chronicle, 'Виктория в новой фракции.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'mention',
        ])->assertOk();

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'relation',
        ])->assertOk()
            ->assertJsonPath('run.candidates.relations.0.status', 'accepted');

        $this->assertDatabaseCount('world_relations', 1);
    }

    public function test_cannot_accept_character_new_entity_from_lore(): void
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
        $lore = $this->createLoreEntry($chronicle, 'Незнакомец.');
        $countBefore = WorldEntity::query()->count();

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'mention',
        ])->assertUnprocessable();

        $this->assertSame($countBefore, WorldEntity::query()->count());
    }

    public function test_discard_mention_does_not_touch_canon(): void
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
        $lore = $this->createLoreEntry($chronicle, 'Элизиум.');
        $countBefore = WorldEntity::query()->count();

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->postJson("/api/extract/{$runId}/candidates/0/discard", [
            'candidate_type' => 'mention',
        ])->assertOk()
            ->assertJsonPath('run.candidates.mentions.0.status', 'discarded');

        $this->assertSame($countBefore, WorldEntity::query()->count());
    }

    public function test_empty_content_with_json_in_thinking_returns_201(): void
    {
        $payload = [
            'mentions' => [
                ['name' => 'Камарилья', 'kind' => 'faction'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ];

        Http::fake([
            config('ollama.url').'/api/chat' => Http::response([
                'done_reason' => 'stop',
                'message' => [
                    'content' => '',
                    'thinking' => json_encode($payload, JSON_UNESCAPED_UNICODE),
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

    public function test_patch_mention_name_rematches_entity(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Камарилия', 'kind' => 'faction'],
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
        );
        $lore = $this->createLoreEntry($chronicle, 'Камарилия правит городом.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->assertSame(
            'new_entity',
            ExtractionRun::query()->findOrFail($runId)->candidates['mentions'][0]['candidate_type'],
        );

        $this->patchJson("/api/extract/{$runId}/candidates/0", [
            'candidate_type' => 'mention',
            'name' => 'Камарилья',
        ])->assertOk()
            ->assertJsonPath('run.candidates.mentions.0.matched_entity_id', $faction->id)
            ->assertJsonMissingPath('run.candidates.mentions.0.candidate_type');
    }

    public function test_patch_mention_kind_updates_directory_kind(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Элизиум', 'kind' => 'faction'],
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

        $this->patchJson("/api/extract/{$runId}/candidates/0", [
            'candidate_type' => 'mention',
            'kind' => 'location',
        ])->assertOk()
            ->assertJsonPath('run.candidates.mentions.0.kind', 'location');
    }

    public function test_accept_mention_with_alias_of_writes_aka_without_new_entity(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Камарильи', 'kind' => 'faction'],
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
        );
        $lore = $this->createLoreEntry($chronicle, 'Камарильи правит городом.');
        $countBefore = WorldEntity::query()->count();

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->patchJson("/api/extract/{$runId}/candidates/0", [
            'candidate_type' => 'mention',
            'alias_of_entity_id' => $faction->id,
        ])->assertOk()
            ->assertJsonPath('run.candidates.mentions.0.alias_of_entity_id', $faction->id);

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'mention',
        ])->assertOk()
            ->assertJsonPath('run.candidates.mentions.0.status', 'merged')
            ->assertJsonPath('run.candidates.mentions.0.matched_entity_id', $faction->id);

        $this->assertSame($countBefore, WorldEntity::query()->count());
        $this->assertDatabaseHas('world_entity_aliases', [
            'entity_id' => $faction->id,
            'alias' => 'Камарильи',
            'alias_type' => 'aka',
        ]);
    }

    public function test_patch_mention_rejects_character_or_event_kind(): void
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
        $lore = $this->createLoreEntry($chronicle, 'Элизиум.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->patchJson("/api/extract/{$runId}/candidates/0", [
            'candidate_type' => 'mention',
            'kind' => 'character',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('kind');

        $this->patchJson("/api/extract/{$runId}/candidates/0", [
            'candidate_type' => 'mention',
            'kind' => 'event',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('kind');
    }

    public function test_patch_mention_alias_of_resolves_pending_relation(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Новая фракция', 'kind' => 'faction'],
            ],
            'relations' => [
                ['source' => 'Виктория', 'target' => 'Новая фракция', 'key' => 'member_of'],
            ],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $entities = $this->app->make(WorldEntityService::class);
        $entities->create($chronicle, WorldEntityType::Character, 'Виктория');
        $faction = $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $lore = $this->createLoreEntry($chronicle, 'Виктория в новой фракции.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->assertFalse(
            ExtractionRun::query()->findOrFail($runId)->candidates['relations'][0]['endpoints_resolved'],
        );

        $this->patchJson("/api/extract/{$runId}/candidates/0", [
            'candidate_type' => 'mention',
            'name' => 'Новая фракция',
            'alias_of_entity_id' => $faction->id,
        ])->assertOk()
            ->assertJsonPath('run.candidates.relations.0.endpoints_resolved', true);

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'mention',
        ])->assertOk();

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'relation',
        ])->assertOk()
            ->assertJsonPath('run.candidates.relations.0.status', 'accepted');

        $this->assertDatabaseCount('world_relations', 1);
    }

    public function test_patch_mention_kind_is_written_on_accept(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Гангрел', 'kind' => 'faction'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Гангрел вышел из Камарильи.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->patchJson("/api/extract/{$runId}/candidates/0", [
            'candidate_type' => 'mention',
            'kind' => 'clan',
        ])->assertOk()
            ->assertJsonPath('run.candidates.mentions.0.kind', 'clan');

        $createdId = $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'mention',
        ])->assertOk()->json('run.candidates.mentions.0.created_entity_id');

        $entity = WorldEntity::query()->with('clan')->findOrFail($createdId);
        $this->assertSame(WorldEntityType::Clan, $entity->entity_type);
        $this->assertNotNull($entity->clan);
    }

    public function test_patch_mention_circle_kind_is_written_on_accept(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Круг Примогенов', 'kind' => 'faction'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Собрался Круг Примогенов.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->patchJson("/api/extract/{$runId}/candidates/0", [
            'candidate_type' => 'mention',
            'kind' => 'circle',
        ])->assertOk()
            ->assertJsonPath('run.candidates.mentions.0.kind', 'circle');

        $createdId = $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'mention',
        ])->assertOk()->json('run.candidates.mentions.0.created_entity_id');

        $entity = WorldEntity::query()->with('circle')->findOrFail($createdId);
        $this->assertSame(WorldEntityType::Circle, $entity->entity_type);
        $this->assertNotNull($entity->circle);
    }

    public function test_patch_mention_ignores_sect_faction_for_non_sect_affiliated_kind(): void
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
        $sect = $this->app->make(WorldEntityService::class)->create(
            $chronicle,
            WorldEntityType::Faction,
            'Камарилья',
        );
        $lore = $this->createLoreEntry($chronicle, 'Элизиум.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->patchJson("/api/extract/{$runId}/candidates/0", [
            'candidate_type' => 'mention',
            'sect_faction_id' => $sect->id,
        ])->assertOk()
            ->assertJsonMissingPath('run.candidates.mentions.0.sect_faction_id');
    }

    public function test_accept_mention_writes_extra_aliases_on_new_entity(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Гангрел', 'kind' => 'faction'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $lore = $this->createLoreEntry($chronicle, 'Гангрел.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->patchJson("/api/extract/{$runId}/candidates/0", [
            'candidate_type' => 'mention',
            'aliases' => ['Gangrel', 'Гангрелы'],
        ])->assertOk()
            ->assertJsonPath('run.candidates.mentions.0.aliases.0', 'Gangrel');

        $createdId = $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'mention',
        ])->assertOk()->json('run.candidates.mentions.0.created_entity_id');

        $this->assertDatabaseHas('world_entity_aliases', [
            'entity_id' => $createdId,
            'alias' => 'Gangrel',
            'alias_type' => 'aka',
        ]);
        $this->assertDatabaseHas('world_entity_aliases', [
            'entity_id' => $createdId,
            'alias' => 'Гангрелы',
            'alias_type' => 'aka',
        ]);
    }

    public function test_accept_alias_of_also_writes_extra_aliases_on_target(): void
    {
        $this->fakeOllamaExtraction([
            'mentions' => [
                ['name' => 'Гангрелы', 'kind' => 'faction'],
            ],
            'relations' => [],
            'events' => [],
            'memories' => [],
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::query()->firstOrFail();
        $clan = $this->app->make(WorldEntityService::class)->create(
            $chronicle,
            WorldEntityType::Clan,
            'Гангрел',
        );
        $lore = $this->createLoreEntry($chronicle, 'Гангрелы ушли.');

        Sanctum::actingAs($storyteller);

        $runId = $this->postJson('/api/extract', [
            'lore_entry_id' => $lore->id,
        ])->assertCreated()->json('extraction_run_id');

        $this->patchJson("/api/extract/{$runId}/candidates/0", [
            'candidate_type' => 'mention',
            'alias_of_entity_id' => $clan->id,
            'aliases' => ['Gangrel'],
        ])->assertOk();

        $this->postJson("/api/extract/{$runId}/candidates/0/accept", [
            'candidate_type' => 'mention',
        ])->assertOk()
            ->assertJsonPath('run.candidates.mentions.0.status', 'merged');

        $this->assertDatabaseHas('world_entity_aliases', [
            'entity_id' => $clan->id,
            'alias' => 'Гангрелы',
            'alias_type' => 'aka',
        ]);
        $this->assertDatabaseHas('world_entity_aliases', [
            'entity_id' => $clan->id,
            'alias' => 'Gangrel',
            'alias_type' => 'aka',
        ]);
    }

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
