<?php

namespace Tests\Feature;

use App\Enums\WorldEntityType;
use App\Models\Chronicle;
use App\Models\WorldRelationType;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use App\World\WorldRelationTypeCatalog;
use App\World\WorldRelationTypeException;
use App\World\WorldRelationTypeValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorldRelationTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_catalog_keys_match_seeded_rows(): void
    {
        $keys = WorldRelationType::query()->pluck('key')->all();
        $expected = WorldRelationTypeCatalog::keys();
        sort($keys);
        sort($expected);

        $this->assertSame($expected, $keys);
        $this->assertNotEmpty($keys);

        $memberOf = WorldRelationType::query()->where('key', 'member_of')->firstOrFail();
        $this->assertFalse($memberOf->symmetric);
        $this->assertFalse($memberOf->transitive);
        $this->assertTrue($memberOf->enabled);
        $this->assertSame(
            ['character', 'clan', 'coterie', 'circle', 'faction'],
            $memberOf->allowedSourceTypeValues(),
        );
        $this->assertSame(['faction'], $memberOf->allowedTargetTypeValues());

        $allied = WorldRelationType::query()->where('key', 'allied_with')->firstOrFail();
        $this->assertTrue($allied->symmetric);
        $this->assertFalse($allied->transitive);

        $partOf = WorldRelationType::query()->where('key', 'part_of')->firstOrFail();
        $this->assertFalse($partOf->symmetric);
        $this->assertTrue($partOf->transitive);
        $this->assertSame('contains', $partOf->inverse_key);
        $this->assertSame(['concept'], $partOf->allowedSourceTypeValues());
    }

    public function test_validator_accepts_allowed_direction_and_rejects_illegal_node_types(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $validator = $this->app->make(WorldRelationTypeValidator::class);

        $character = $service->create($chronicle, WorldEntityType::Character, 'Виктория');
        $faction = $service->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $location = $service->create($chronicle, WorldEntityType::Location, 'Прага');
        $item = $service->create($chronicle, WorldEntityType::Item, 'Клинок');
        $event = $service->create($chronicle, WorldEntityType::Event, 'Саботаж Маскарада');

        $memberOf = WorldRelationType::query()->where('key', 'member_of')->firstOrFail();
        $locatedAt = WorldRelationType::query()->where('key', 'located_at')->firstOrFail();
        $owns = WorldRelationType::query()->where('key', 'owns')->firstOrFail();
        $witnessed = WorldRelationType::query()->where('key', 'witnessed')->firstOrFail();

        $validator->assertCompatible($memberOf, $character, $faction);
        $validator->assertCompatible($locatedAt, $item, $location);
        $validator->assertCompatible($owns, $character, $item);
        $validator->assertCompatible($witnessed, $character, $event);

        $this->expectException(WorldRelationTypeException::class);
        $validator->assertCompatible($memberOf, $character, $location);
    }

    public function test_validator_rejects_wrong_source_type(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $faction = $service->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $character = $service->create($chronicle, WorldEntityType::Character, 'Виктория');
        $knows = WorldRelationType::query()->where('key', 'knows')->firstOrFail();

        $this->expectException(WorldRelationTypeException::class);
        $this->app->make(WorldRelationTypeValidator::class)
            ->assertCompatible($knows, $faction, $character);
    }

    public function test_disabled_type_is_rejected(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $character = $service->create($chronicle, WorldEntityType::Character, 'Виктория');
        $faction = $service->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $memberOf = WorldRelationType::query()->where('key', 'member_of')->firstOrFail();
        $memberOf->update(['enabled' => false]);

        $this->expectException(WorldRelationTypeException::class);
        $this->app->make(WorldRelationTypeValidator::class)
            ->assertCompatible($memberOf->fresh(), $character, $faction);
    }

    public function test_validator_rejects_mixed_chronicles(): void
    {
        $left = Chronicle::factory()->create();
        $right = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $character = $service->create($left, WorldEntityType::Character, 'Виктория');
        $faction = $service->create($right, WorldEntityType::Faction, 'Камарилья');
        $memberOf = WorldRelationType::query()->where('key', 'member_of')->firstOrFail();

        $this->expectException(MixedChronicleException::class);
        $this->app->make(WorldRelationTypeValidator::class)
            ->assertCompatible($memberOf, $character, $faction);
    }

    public function test_new_type_is_a_row_not_a_schema_change(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $character = $service->create($chronicle, WorldEntityType::Character, 'Виктория');
        $concept = $service->create($chronicle, WorldEntityType::Concept, 'Маскарад');
        $location = $service->create($chronicle, WorldEntityType::Location, 'Прага');

        $worships = WorldRelationType::factory()->create([
            'key' => 'worships',
            'display_name' => 'Worships',
            'allowed_source_types' => [WorldEntityType::Character->value],
            'allowed_target_types' => [WorldEntityType::Concept->value],
        ]);

        $validator = $this->app->make(WorldRelationTypeValidator::class);
        $validator->assertCompatible($worships, $character, $concept);

        $this->assertTrue(Schema::hasTable('world_relations'));
        $this->assertTrue(Schema::hasColumn('world_relation_types', 'key'));

        $this->expectException(WorldRelationTypeException::class);
        $validator->assertCompatible($worships, $character, $location);
    }

    public function test_caused_allows_event_to_event(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $fire = $service->create($chronicle, WorldEntityType::Event, 'Пожар');
        $panic = $service->create($chronicle, WorldEntityType::Event, 'Паника двора');
        $caused = WorldRelationType::query()->where('key', 'caused')->firstOrFail();

        $this->app->make(WorldRelationTypeValidator::class)
            ->assertCompatible($caused, $fire, $panic);

        $this->assertContains(WorldEntityType::Event->value, $caused->allowedSourceTypeValues());
    }
}
