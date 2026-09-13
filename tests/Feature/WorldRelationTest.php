<?php

namespace Tests\Feature;

use App\Enums\WorldEntityType;
use App\Models\Chronicle;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use App\World\WorldRelationException;
use App\World\WorldRelationService;
use App\World\WorldRelationTypeException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorldRelationTest extends TestCase
{
    use RefreshDatabase;

    public function test_graph_table_and_adjacency_indexes_exist(): void
    {
        $this->assertTrue(Schema::hasTable('world_relations'));
        $this->assertTrue(Schema::hasColumns('world_relations', [
            'chronicle_id',
            'source_entity_id',
            'target_entity_id',
            'relation_type_id',
            'weight',
            'note',
            'started_at',
            'ended_at',
            'provenance',
        ]));

        $indexes = collect(Schema::getIndexes('world_relations'))->pluck('name');
        $this->assertTrue($indexes->contains('world_relations_source_type_index'));
        $this->assertTrue($indexes->contains('world_relations_target_type_index'));
    }

    public function test_any_entity_type_can_join_the_directed_graph(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $relations = $this->app->make(WorldRelationService::class);

        $character = $service->create($chronicle, WorldEntityType::Character, 'Виктория');
        $faction = $service->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $location = $service->create($chronicle, WorldEntityType::Location, 'Прага');
        $item = $service->create($chronicle, WorldEntityType::Item, 'Клинок');
        $concept = $service->create($chronicle, WorldEntityType::Concept, 'Маскарад');
        $event = $service->create($chronicle, WorldEntityType::Event, 'Саботаж');

        $relations->relate($character, $faction, $this->type('member_of'));
        $relations->relate($character, $location, $this->type('located_at'));
        $relations->relate($character, $item, $this->type('owns'));
        $relations->relate($character, $concept, $this->type('knows'));
        $relations->relate($character, $event, $this->type('witnessed'));
        $relations->relate($event, $location, $this->type('located_at'));

        $this->assertDatabaseCount('world_relations', 6);
        $this->assertSame(6, WorldRelation::query()->active()->count());
    }

    public function test_symmetric_type_does_not_insert_a_reverse_row_but_query_sees_both_sides(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $relations = $this->app->make(WorldRelationService::class);
        $camarilla = $service->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $anarchs = $service->create($chronicle, WorldEntityType::Faction, 'Анархи');
        $allied = $this->type('allied_with');

        $edge = $relations->relate($camarilla, $anarchs, $allied, weight: 0.8, note: 'Перемирие.');

        $this->assertDatabaseCount('world_relations', 1);
        $this->assertSame($camarilla->id, $edge->source_entity_id);
        $this->assertSame($anarchs->id, $edge->target_entity_id);

        $fromCamarilla = $relations->neighbors($camarilla, $allied);
        $fromAnarchs = $relations->neighbors($anarchs, $allied);

        $this->assertCount(1, $fromCamarilla);
        $this->assertCount(1, $fromAnarchs);
        $this->assertTrue($fromAnarchs->first()->is($edge));
        $this->assertSame($camarilla->id, $fromAnarchs->first()->other($anarchs)->id);
    }

    public function test_asymmetric_incoming_edge_is_not_a_neighbor(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $relations = $this->app->make(WorldRelationService::class);
        $character = $service->create($chronicle, WorldEntityType::Character, 'Виктория');
        $faction = $service->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $memberOf = $this->type('member_of');

        $relations->relate($character, $faction, $memberOf);

        $this->assertCount(1, $relations->neighbors($character, $memberOf));
        $this->assertCount(0, $relations->neighbors($faction, $memberOf));
    }

    public function test_self_loop_and_mixed_chronicle_and_illegal_types_are_rejected(): void
    {
        $left = Chronicle::factory()->create();
        $right = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $relations = $this->app->make(WorldRelationService::class);
        $victoria = $service->create($left, WorldEntityType::Character, 'Виктория');
        $other = $service->create($right, WorldEntityType::Faction, 'Камарилья');
        $knows = $this->type('knows');
        $memberOf = $this->type('member_of');

        try {
            $relations->relate($victoria, $victoria, $knows);
            $this->fail('Expected self-loop rejection.');
        } catch (WorldRelationException $e) {
            $this->assertSame('A world relation cannot be a self-loop.', $e->getMessage());
        }

        try {
            $relations->relate($victoria, $other, $memberOf);
            $this->fail('Expected mixed chronicle rejection.');
        } catch (MixedChronicleException) {
            $this->assertTrue(true);
        }

        $location = $service->create($left, WorldEntityType::Location, 'Прага');
        $this->expectException(WorldRelationTypeException::class);
        $relations->relate($victoria, $location, $memberOf);
    }

    public function test_active_duplicate_is_rejected_but_ended_edge_can_be_replaced(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $relations = $this->app->make(WorldRelationService::class);
        $character = $service->create($chronicle, WorldEntityType::Character, 'Виктория');
        $faction = $service->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $memberOf = $this->type('member_of');

        $first = $relations->relate($character, $faction, $memberOf);

        try {
            $relations->relate($character, $faction, $memberOf);
            $this->fail('Expected duplicate rejection.');
        } catch (WorldRelationException) {
            $this->assertTrue(true);
        }

        $relations->end($first);
        $second = $relations->relate($character, $faction, $memberOf, note: 'Вернулась.');

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotNull($first->fresh()->ended_at);
        $this->assertTrue($second->isActive());
        $this->assertSame(1, WorldRelation::query()->active()->count());
    }

    public function test_symmetric_reverse_duplicate_is_rejected(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $relations = $this->app->make(WorldRelationService::class);
        $camarilla = $service->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $sabbat = $service->create($chronicle, WorldEntityType::Faction, 'Саббат');
        $hostile = $this->type('hostile_to');

        $relations->relate($camarilla, $sabbat, $hostile);

        $this->expectException(WorldRelationException::class);
        $relations->relate($sabbat, $camarilla, $hostile);
    }

    public function test_database_rejects_self_loop(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entity = $this->app->make(WorldEntityService::class)
            ->create($chronicle, WorldEntityType::Character, 'Виктория');

        $this->expectException(QueryException::class);
        WorldRelation::query()->create([
            'chronicle_id' => $chronicle->id,
            'source_entity_id' => $entity->id,
            'target_entity_id' => $entity->id,
            'relation_type_id' => $this->type('knows')->id,
            'weight' => 1,
        ]);
    }

    public function test_factory_creates_a_valid_edge(): void
    {
        $relation = WorldRelation::factory()->create();

        $this->assertSame($relation->source->chronicle_id, $relation->target->chronicle_id);
        $this->assertTrue($relation->isActive());
    }

    private function type(string $key): WorldRelationType
    {
        return WorldRelationType::query()->where('key', $key)->firstOrFail();
    }
}
