<?php

namespace Tests\Feature;

use App\Enums\WorldEntityAliasType;
use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\WorldEntity;
use App\Models\WorldEntityAlias;
use App\World\CannotDeleteWorldEntityException;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorldEntityTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_creates_typed_identity_and_aliases_atomically(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);

        $entity = $service->create(
            $chronicle,
            WorldEntityType::Location,
            'Элизиум',
            'Зал собраний Камарильи.',
            ['Зал Принца', 'Канцелярия'],
            'ru',
        );

        $this->assertSame(WorldEntityType::Location, $entity->entity_type);
        $this->assertSame($chronicle->id, $entity->chronicle_id);
        $this->assertSame('элизиум', $entity->slug);
        $this->assertSame(WorldEntityStatus::Active, $entity->status);
        $this->assertCount(3, $entity->aliases);
        $this->assertNotNull($entity->canonicalAlias);
        $this->assertSame(WorldEntityAliasType::Canonical, $entity->canonicalAlias->alias_type);
        $this->assertNotNull($entity->location);
        $this->assertSame($entity->id, $entity->location->id);
        $this->assertSame(
            $entity->id,
            $service->findByAlias($chronicle, 'Зал Принца')?->id,
        );
    }

    public function test_entities_are_isolated_by_chronicle_and_resolved_by_stable_id(): void
    {
        $chronicleA = Chronicle::factory()->create();
        $chronicleB = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);

        $elisiumA = $service->create($chronicleA, WorldEntityType::Location, 'Элизиум');
        $elisiumB = $service->create($chronicleB, WorldEntityType::Location, 'Элизиум');

        $this->assertNotSame($elisiumA->id, $elisiumB->id);
        $this->assertSame($elisiumA->id, $service->findByAlias($chronicleA, 'элизиум')?->id);
        $this->assertSame($elisiumB->id, $service->findByAlias($chronicleB, 'Элизиум')?->id);
        $this->assertNull($service->findByAlias($chronicleA, 'Гавань'));

        $this->expectException(MixedChronicleException::class);
        $service->assertSameChronicle($chronicleA, $elisiumA, $elisiumB);
    }

    public function test_duplicate_normalized_alias_in_the_same_chronicle_rolls_back(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $service->create($chronicle, WorldEntityType::Location, 'Док', aliases: ['Гавань']);

        try {
            $service->create($chronicle, WorldEntityType::Faction, 'Портовый клан', aliases: ['гавань']);
            $this->fail('Duplicate alias must be rejected.');
        } catch (UniqueConstraintViolationException) {
            // expected
        }

        $this->assertSame(1, WorldEntity::query()->where('chronicle_id', $chronicle->id)->count());
        $this->assertSame(2, WorldEntityAlias::query()->where('chronicle_id', $chronicle->id)->count());
    }

    public function test_alias_cannot_belong_to_another_chronicle(): void
    {
        $chronicleA = Chronicle::factory()->create();
        $chronicleB = Chronicle::factory()->create();
        $entity = $this->app->make(WorldEntityService::class)
            ->create($chronicleA, WorldEntityType::Item, 'Клинок');

        $this->expectException(QueryException::class);

        WorldEntityAlias::query()->create([
            'entity_id' => $entity->id,
            'chronicle_id' => $chronicleB->id,
            'alias' => 'Меч',
            'normalized_alias' => 'меч',
            'alias_type' => WorldEntityAliasType::Aka,
        ]);
    }

    public function test_world_entities_are_archived_instead_of_deleted(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $entity = $service->create($chronicle, WorldEntityType::Concept, 'Маскарад');

        $archived = $service->archive($entity);

        $this->assertSame(WorldEntityStatus::Archived, $archived->status);
        $this->assertNotNull($archived->archived_at);
        $this->assertSame($entity->id, $service->findByAlias($chronicle, 'Маскарад')?->id);

        $this->expectException(CannotDeleteWorldEntityException::class);
        $entity->delete();
    }

    public function test_database_rejects_physical_delete_of_a_world_entity(): void
    {
        $entity = WorldEntity::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('world_entities')->where('id', $entity->id)->delete();
    }

    public function test_archiving_a_character_marks_the_typed_row_inactive(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);
        $entity = $service->create($chronicle, WorldEntityType::Character, 'Виктория');

        $this->assertTrue((bool) Character::query()->findOrFail($entity->id)->is_active);

        $service->archive($entity->fresh());

        $this->assertFalse((bool) Character::query()->findOrFail($entity->id)->is_active);
        $this->assertSame(WorldEntityStatus::Archived, $entity->fresh()->status);

        $restored = $service->restore($entity->fresh());
        $this->assertSame(WorldEntityStatus::Active, $restored->status);
        $this->assertNull($restored->archived_at);
        $this->assertTrue((bool) Character::query()->findOrFail($entity->id)->is_active);
    }
}
