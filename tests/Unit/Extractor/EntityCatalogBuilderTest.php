<?php

namespace Tests\Unit\Extractor;

use App\Enums\WorldEntityType;
use App\Extractor\EntityCatalogBuilder;
use App\Models\Chronicle;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityCatalogBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_includes_entities_mentioned_in_slice_text(): void
    {
        $chronicle = Chronicle::query()->firstOrFail();
        $entities = $this->app->make(WorldEntityService::class);
        $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $entities->create($chronicle, WorldEntityType::Location, 'Элизиум');

        $lines = $this->app->make(EntityCatalogBuilder::class)->build(
            $chronicle,
            'Виктория пришла в Элизиум.',
        );

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Элизиум', $lines[0]);
    }

    public function test_build_always_includes_linked_entity_ids(): void
    {
        $chronicle = Chronicle::query()->firstOrFail();
        $entities = $this->app->make(WorldEntityService::class);
        $linked = $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $entities->create($chronicle, WorldEntityType::Location, 'Элизиум');

        $lines = $this->app->make(EntityCatalogBuilder::class)->build(
            $chronicle,
            'Статья без имён.',
            [(int) $linked->id],
        );

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Камарилья', $lines[0]);
    }
}
