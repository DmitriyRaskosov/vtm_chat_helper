<?php

namespace Tests\Unit\Extractor;

use App\Enums\WorldEntityType;
use App\Extractor\EntityCatalogBuilder;
use App\Extractor\ExtractorTokenBudget;
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

    public function test_short_clan_name_does_not_match_inside_longer_antitribu_name(): void
    {
        $chronicle = Chronicle::query()->firstOrFail();
        $entities = $this->app->make(WorldEntityService::class);
        $entities->create($chronicle, WorldEntityType::Coterie, 'Котерия Бруха');
        $entities->create($chronicle, WorldEntityType::Faction, 'Шабаш');

        $lines = $this->app->make(EntityCatalogBuilder::class)->build(
            $chronicle,
            'Отступники Бруха служат Шабаш.',
        );

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Шабаш', $lines[0]);
        $this->assertStringNotContainsString('Бруха', $lines[0]);
    }

    public function test_catalog_is_trimmed_to_profile_token_budget(): void
    {
        config([
            'extractor.characters_per_token' => 2,
            'extractor.profiles.lore.catalog_tokens' => 20,
        ]);

        $chronicle = Chronicle::query()->firstOrFail();
        $entities = $this->app->make(WorldEntityService::class);

        for ($index = 1; $index <= 5; $index++) {
            $entities->create(
                $chronicle,
                WorldEntityType::Location,
                "Место {$index}",
                aliases: ["Alias {$index}"],
            );
        }

        $lines = $this->app->make(EntityCatalogBuilder::class)->build(
            $chronicle,
            implode(' ', array_map(fn (int $index): string => "Место {$index}", range(1, 5))),
            profile: 'lore',
        );

        $budget = $this->app->make(ExtractorTokenBudget::class);
        $usedTokens = 0;
        foreach ($lines as $line) {
            $usedTokens += $budget->estimateTokens($line."\n");
        }

        $this->assertNotEmpty($lines);
        $this->assertLessThanOrEqual(20, $usedTokens);
        $this->assertLessThan(5, count($lines));
    }
}
