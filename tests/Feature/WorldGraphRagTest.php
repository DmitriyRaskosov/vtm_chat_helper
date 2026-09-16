<?php

namespace Tests\Feature;

use App\Character\CharacterLoreKnowledgeService;
use App\Enums\CharacterKnowledgeLevel;
use App\Enums\CharacterMemoryNodeType;
use App\Enums\LoreAccessLevel;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Enums\WorldEntityType;
use App\Enums\WorldEventStatus;
use App\Enums\WorldEventType;
use App\Enums\WorldEventVisibility;
use App\Lore\LoreEntryService;
use App\Lore\LoreIndexer;
use App\Memory\CharacterMemoryService;
use App\Memory\MemoryBridgeService;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use App\Models\WorldEvent;
use App\Models\WorldRelationType;
use App\World\WorldEntityService;
use App\World\WorldEventService;
use App\World\WorldGraphRag;
use App\World\WorldRelationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldGraphRagTest extends TestCase
{
    use RefreshDatabase;

    public function test_character_faction_location_event_path_within_depth_two(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $graph = $this->court($chronicle, $scene);
        $bundle = $this->app->make(WorldGraphRag::class)->expand(
            $chronicle,
            [(int) $graph['victoria']->id],
            $graph['victoria'],
        );
        $ids = collect($bundle->entities)->pluck('id')->all();

        $this->assertContains($graph['victoria']->id, $ids);
        $this->assertContains($graph['camarilla']->id, $ids);
        $this->assertContains($graph['prague']->id, $ids);
        $this->assertContains($graph['sabotage']->id, $ids);
        $this->assertTrue(collect($bundle->relations)->contains('typeKey', 'member_of'));
        $this->assertTrue(collect($bundle->relations)->contains('typeKey', 'controls'));
        $this->assertSame([], $bundle->affiliations);
        $this->assertSame([], $bundle->relationships);
        $this->assertTrue(collect($bundle->events)->contains('id', $graph['sabotage']->id));
        $this->assertTrue(collect($bundle->entities)->firstWhere('id', $graph['victoria']->id)?->seed);
    }

    public function test_part_of_reaches_child_concept_from_parent_via_inverse_key(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $relations = $this->app->make(WorldRelationService::class);
        $codex = $entities->create($chronicle, WorldEntityType::Concept, 'Кодекс Милана');
        $article = $entities->create($chronicle, WorldEntityType::Concept, 'Статья I');
        $partOf = WorldRelationType::query()->where('key', 'part_of')->firstOrFail();
        $relations->relate($article, $codex, $partOf);

        $bundle = $this->app->make(WorldGraphRag::class)->expand($chronicle, [(int) $codex->id]);
        $ids = collect($bundle->entities)->pluck('id')->all();

        $this->assertContains($codex->id, $ids);
        $this->assertContains($article->id, $ids);
        $this->assertTrue(collect($bundle->relations)->contains('typeKey', 'part_of'));
        $this->assertDatabaseCount('world_relations', 1);
    }

    public function test_cycles_stay_bounded(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $relations = $this->app->make(WorldRelationService::class);
        $a = $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $b = $entities->create($chronicle, WorldEntityType::Faction, 'Анархи');
        $c = $entities->create($chronicle, WorldEntityType::Faction, 'Шабаш');
        $allied = WorldRelationType::query()->where('key', 'allied_with')->firstOrFail();
        $relations->relate($a, $b, $allied);
        $relations->relate($b, $c, $allied);
        $relations->relate($c, $a, $allied);

        $bundle = $this->app->make(WorldGraphRag::class)->expand($chronicle, [(int) $a->id]);
        $ids = collect($bundle->entities)->pluck('id');

        $this->assertCount(3, $ids->unique());
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], $ids->all());
        $this->assertLessThanOrEqual(3, count($bundle->relations));
    }

    public function test_foreign_chronicle_entities_are_not_reached(): void
    {
        $home = Chronicle::factory()->create();
        $away = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $relations = $this->app->make(WorldRelationService::class);
        $homeNpc = $entities->create($home, WorldEntityType::Character, 'Виктория');
        $homeFaction = $entities->create($home, WorldEntityType::Faction, 'Камарилья');
        $awayNpc = $entities->create($away, WorldEntityType::Character, 'Маркус');
        $awayFaction = $entities->create($away, WorldEntityType::Faction, 'Камарилья Берлина');
        $member = WorldRelationType::query()->where('key', 'member_of')->firstOrFail();
        $relations->relate($homeNpc, $homeFaction, $member);
        $relations->relate($awayNpc, $awayFaction, $member);

        $bundle = $this->app->make(WorldGraphRag::class)->expand($home, [(int) $homeNpc->id]);
        $ids = collect($bundle->entities)->pluck('id')->all();

        $this->assertContains($homeNpc->id, $ids);
        $this->assertContains($homeFaction->id, $ids);
        $this->assertNotContains($awayNpc->id, $ids);
        $this->assertNotContains($awayFaction->id, $ids);
        $this->assertTrue(collect($bundle->relations)->every(
            fn ($relation): bool => in_array($relation->sourceEntityId, $ids, true)
                && in_array($relation->targetEntityId, $ids, true),
        ));
    }

    public function test_ungranted_lore_and_storyteller_only_events_do_not_leak_to_npc(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $graph = $this->court($chronicle, $scene);
        $secretLore = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Тайна Камарильи',
            'canonical_text' => 'Внутренний круг служит Шабашу.',
            'status' => LoreEntryStatus::Approved,
            'visibility' => LoreVisibility::StorytellerOnly,
            'classification' => LoreAccessLevel::L5,
        ], 'v1');
        $this->app->make(LoreEntryService::class)->attachEntity($secretLore, $graph['camarilla']);
        $this->app->make(LoreIndexer::class)->rebuildApproved($secretLore);

        $secretEvent = WorldEvent::query()->findOrFail(
            $this->app->make(WorldEntityService::class)->create(
                $chronicle,
                WorldEntityType::Event,
                'Тайная аудиенция',
                typed: [
                    'event_type' => WorldEventType::Political,
                    'status' => WorldEventStatus::Canonical,
                    'visibility' => WorldEventVisibility::StorytellerOnly,
                ],
            )->id,
        );
        $this->app->make(WorldRelationService::class)->relate(
            WorldEntity::query()->findOrFail($graph['victoria']->id),
            WorldEntity::query()->findOrFail($secretEvent->id),
            WorldRelationType::query()->where('key', 'participated_in')->firstOrFail(),
        );

        $npcBundle = $this->app->make(WorldGraphRag::class)->expand(
            $chronicle,
            [(int) $graph['victoria']->id],
            $graph['victoria'],
        );
        $storytellerBundle = $this->app->make(WorldGraphRag::class)->expand(
            $chronicle,
            [(int) $graph['victoria']->id],
        );

        $this->assertFalse(collect($npcBundle->entities)->contains('id', $secretEvent->id));
        $this->assertTrue(collect($npcBundle->loreChunks)->isEmpty());
        $this->assertFalse(collect($npcBundle->events)->contains('id', $secretEvent->id));
        $this->assertTrue(collect($storytellerBundle->entities)->contains('id', $secretEvent->id));
        $this->assertTrue(collect($storytellerBundle->loreChunks)->contains(
            fn (array $chunk): bool => $chunk['lore_entry_id'] === $secretLore->id,
        ));

        $this->app->make(CharacterLoreKnowledgeService::class)->grant(
            $graph['victoria'],
            $secretLore,
            CharacterKnowledgeLevel::Known,
        );
        $granted = $this->app->make(WorldGraphRag::class)->expand(
            $chronicle,
            [(int) $graph['victoria']->id],
            $graph['victoria'],
        );
        $this->assertTrue(collect($granted->loreChunks)->contains(
            fn (array $chunk): bool => $chunk['lore_entry_id'] === $secretLore->id,
        ));
        $this->assertFalse(collect($granted->entities)->contains('id', $secretEvent->id));
    }

    public function test_memory_bridge_and_alias_seed_world_graph_without_guessing_from_text(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $victoria = Character::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Character, 'Виктория')->id,
        );
        $elysium = $entities->create($chronicle, WorldEntityType::Location, 'Пражский Элизиум');
        $memory = $this->app->make(CharacterMemoryService::class)->remember(
            $victoria,
            'Двор собирался в Пражском Элизиуме.',
            CharacterMemoryNodeType::Place,
        );

        $withoutBridge = $this->app->make(WorldGraphRag::class)->collectSeeds(
            $chronicle,
            $victoria,
            aliasQueries: ['Пражский Элизиум'],
            memoryNodes: collect([$memory]),
        );
        $this->assertContains($elysium->id, $withoutBridge);

        $textOnly = $this->app->make(WorldGraphRag::class)->collectSeeds(
            $chronicle,
            $victoria,
            memoryNodes: collect([$memory]),
        );
        $this->assertNotContains($elysium->id, $textOnly);

        $this->app->make(MemoryBridgeService::class)->linkEntity($memory, $elysium);
        $withBridge = $this->app->make(WorldGraphRag::class)->collectSeeds(
            $chronicle,
            $victoria,
            memoryNodes: collect([$memory]),
        );
        $this->assertContains($elysium->id, $withBridge);
    }

    /**
     * @return array{victoria: Character, camarilla: WorldEntity, prague: WorldEntity, sabotage: WorldEvent}
     */
    private function court(Chronicle $chronicle, Scene $scene): array
    {
        $entities = $this->app->make(WorldEntityService::class);
        $relations = $this->app->make(WorldRelationService::class);
        $victoria = Character::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Character, 'Виктория-'.uniqid())->id,
        );
        $camarilla = $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $prague = $entities->create($chronicle, WorldEntityType::Location, 'Прага');
        $sabotageIdentity = $entities->create(
            $chronicle,
            WorldEntityType::Event,
            'Саботаж Маскарада',
            typed: [
                'event_type' => WorldEventType::Political,
                'status' => WorldEventStatus::Proposed,
                'visibility' => WorldEventVisibility::Public,
                'scene_id' => $scene->id,
                'importance' => 4,
            ],
        );
        $sabotage = WorldEvent::query()->findOrFail($sabotageIdentity->id);
        $this->app->make(WorldEventService::class)->approve($sabotage, User::factory()->storyteller()->create());

        $relations->relate(
            WorldEntity::query()->findOrFail($victoria->id),
            $camarilla,
            WorldRelationType::query()->where('key', 'member_of')->firstOrFail(),
            metadata: ['stance' => 'allied', 'loyalty' => 4, 'trust' => 3, 'role' => 'неофит'],
        );
        $relations->relate(
            $camarilla,
            $prague,
            WorldRelationType::query()->where('key', 'controls')->firstOrFail(),
        );
        $relations->relate(
            WorldEntity::query()->findOrFail($victoria->id),
            $sabotageIdentity,
            WorldRelationType::query()->where('key', 'participated_in')->firstOrFail(),
        );
        $this->app->make(WorldEventService::class)->occurredAt($sabotage, $prague);

        return [
            'victoria' => $victoria,
            'camarilla' => $camarilla,
            'prague' => $prague,
            'sabotage' => $sabotage->fresh(),
        ];
    }
}
