<?php

namespace Tests\Feature;

use App\Character\CharacterAffiliationRevisionException;
use App\Character\CharacterAffiliationService;
use App\Enums\CharacterAffiliationStance;
use App\Enums\CharacterAffiliationType;
use App\Enums\FactionType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\CharacterAffiliation;
use App\Models\CharacterAffiliationChange;
use App\Models\Chronicle;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldRelation;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class CharacterAffiliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_affiliation_to_faction_location_item_and_concept_joins_the_graph(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $affiliations = $this->app->make(CharacterAffiliationService::class);
        $character = Character::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Character, 'Виктория')->id,
        );
        $faction = $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $location = $entities->create($chronicle, WorldEntityType::Location, 'Прага');
        $item = $entities->create($chronicle, WorldEntityType::Item, 'Клинок');
        $concept = $entities->create($chronicle, WorldEntityType::Concept, 'Маскарад');

        $camarilla = $affiliations->attach(
            $character,
            $faction,
            CharacterAffiliationType::Member,
            CharacterAffiliationStance::Allied,
            trust: 3,
            loyalty: 4,
            role: 'неофит',
        );
        $affiliations->attach($character, $location, CharacterAffiliationType::Resident, CharacterAffiliationStance::Neutral);
        $affiliations->attach($character, $item, CharacterAffiliationType::Owner, CharacterAffiliationStance::Allied);
        $affiliations->attach($character, $concept, CharacterAffiliationType::Adherent, CharacterAffiliationStance::Wary, fear: 2);

        $this->assertDatabaseCount('character_affiliations', 4);
        $this->assertDatabaseCount('world_relations', 4);
        $this->assertSame($faction->id, $camarilla->target_entity_id);
        $this->assertSame(3, $camarilla->trust);
        $this->assertSame(4, $camarilla->loyalty);
        $this->assertSame('неофит', $camarilla->role);
        $this->assertSame(1, $camarilla->revision);
        $this->assertTrue(WorldRelation::query()->findOrFail($camarilla->id)->characterAffiliation->is($camarilla));

        $found = CharacterAffiliation::query()
            ->where('character_id', $character->id)
            ->where('affiliation_type', CharacterAffiliationType::Member)
            ->where('trust', '>=', 3)
            ->first();
        $this->assertTrue($camarilla->is($found));
    }

    public function test_apply_journals_metrics_in_one_transaction_with_revision(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $affiliations = $this->app->make(CharacterAffiliationService::class);
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->firstOrFail();
        $character = Character::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Character, 'Виктория')->id,
        );
        $faction = $entities->create($chronicle, WorldEntityType::Faction, 'Камарилья');
        $affiliation = $affiliations->attach(
            $character,
            $faction,
            CharacterAffiliationType::Member,
            CharacterAffiliationStance::Allied,
        );

        $updated = $affiliations->apply(
            $affiliation,
            [
                'trust' => 5,
                'stance' => CharacterAffiliationStance::Wary,
                'note' => 'Двор настороже.',
            ],
            expectedRevision: 1,
            reason: 'Интрига примогена.',
            scene: $scene,
            changedBy: $storyteller,
        );

        $this->assertSame(2, $updated->revision);
        $this->assertSame(5, $updated->trust);
        $this->assertSame(CharacterAffiliationStance::Wary, $updated->stance);
        $this->assertDatabaseCount('character_affiliation_changes', 3);
        $this->assertDatabaseHas('character_affiliation_changes', [
            'affiliation_id' => $affiliation->id,
            'field' => 'trust',
            'reason' => 'Интрига примогена.',
            'scene_id' => $scene->id,
            'changed_by' => $storyteller->id,
            'revision' => 2,
        ]);

        $trust = CharacterAffiliationChange::query()->where('field', 'trust')->firstOrFail();
        $this->assertSame(0, $trust->old_value['v']);
        $this->assertSame(5, $trust->new_value['v']);
    }

    public function test_stale_revision_is_rejected(): void
    {
        $affiliation = CharacterAffiliation::factory()->create();
        $service = $this->app->make(CharacterAffiliationService::class);
        $service->apply($affiliation, ['trust' => 1], expectedRevision: 1);

        $this->expectException(CharacterAffiliationRevisionException::class);
        $service->apply($affiliation, ['trust' => 2], expectedRevision: 1);
    }

    public function test_character_and_event_targets_are_rejected(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $affiliations = $this->app->make(CharacterAffiliationService::class);
        $victoria = Character::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Character, 'Виктория')->id,
        );
        $marcus = $entities->create($chronicle, WorldEntityType::Character, 'Маркус');
        $event = $entities->create($chronicle, WorldEntityType::Event, 'Саботаж');

        try {
            $affiliations->attach(
                $victoria,
                $marcus,
                CharacterAffiliationType::Other,
                CharacterAffiliationStance::Neutral,
            );
            $this->fail('Expected rejection of a character target.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('faction, location, item, or concept', $e->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $affiliations->attach(
            $victoria,
            $event,
            CharacterAffiliationType::Other,
            CharacterAffiliationStance::Neutral,
        );
    }

    public function test_mixed_chronicle_target_is_rejected(): void
    {
        $left = Chronicle::factory()->create();
        $right = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $character = Character::query()->findOrFail(
            $entities->create($left, WorldEntityType::Character, 'Виктория')->id,
        );
        $faction = $entities->create($right, WorldEntityType::Faction, 'Камарилья');

        $this->expectException(MixedChronicleException::class);
        $this->app->make(CharacterAffiliationService::class)->attach(
            $character,
            $faction,
            CharacterAffiliationType::Member,
            CharacterAffiliationStance::Allied,
        );
    }

    public function test_metric_range_is_enforced(): void
    {
        $affiliation = CharacterAffiliation::factory()->create();

        $this->expectException(InvalidArgumentException::class);
        $this->app->make(CharacterAffiliationService::class)
            ->apply($affiliation, ['trust' => 6], expectedRevision: 1);
    }

    public function test_set_sect_replaces_membership_and_keeps_coterie(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $affiliations = $this->app->make(CharacterAffiliationService::class);
        $character = Character::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Character, 'Виктория')->id,
        );
        $camarilla = $entities->create(
            $chronicle,
            WorldEntityType::Faction,
            'Камарилья',
            typed: ['faction_type' => FactionType::Sect],
        );
        $anarchs = $entities->create(
            $chronicle,
            WorldEntityType::Faction,
            'Анархи',
            typed: ['faction_type' => FactionType::Sect],
        );
        $coterie = $entities->create(
            $chronicle,
            WorldEntityType::Faction,
            'Котерия Праги',
            typed: ['faction_type' => FactionType::Coterie],
        );

        $coterieRow = $affiliations->attach(
            $character,
            $coterie,
            CharacterAffiliationType::Member,
            CharacterAffiliationStance::Allied,
        );
        $first = $affiliations->setSect($character, $camarilla);
        $second = $affiliations->setSect($character, $anarchs);

        $this->assertNotSame($first->id, $second->id);
        $this->assertNotNull(WorldRelation::query()->findOrFail($first->id)->ended_at);
        $this->assertTrue(WorldRelation::query()->findOrFail($second->id)->isActive());
        $this->assertTrue(WorldRelation::query()->findOrFail($coterieRow->id)->isActive());
        $this->assertSame($anarchs->id, $affiliations->activeSect($character)?->target_entity_id);

        $this->assertNull($affiliations->setSect($character, null));
        $this->assertNull($affiliations->activeSect($character));
        $this->assertTrue(WorldRelation::query()->findOrFail($coterieRow->id)->isActive());
    }

    public function test_set_sect_rejects_circle_faction(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $affiliations = $this->app->make(CharacterAffiliationService::class);
        $character = Character::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Character, 'Виктория')->id,
        );
        $circle = $entities->create(
            $chronicle,
            WorldEntityType::Faction,
            'Круг Примогенов',
            typed: ['faction_type' => FactionType::Circle],
        );

        $this->expectException(InvalidArgumentException::class);
        $affiliations->setSect($character, $circle);
    }

    public function test_set_haven_is_unique_resident_location(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $affiliations = $this->app->make(CharacterAffiliationService::class);
        $character = Character::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Character, 'Виктория')->id,
        );
        $elisium = $entities->create($chronicle, WorldEntityType::Location, 'Элизиум');
        $haven = $entities->create($chronicle, WorldEntityType::Location, 'Гавань');

        $first = $affiliations->setHaven($character, $elisium);
        $second = $affiliations->setHaven($character, $haven);

        $this->assertNotNull(WorldRelation::query()->findOrFail($first->id)->ended_at);
        $this->assertSame($haven->id, $affiliations->activeHaven($character)?->target_entity_id);
        $this->assertSame('located_at', WorldRelation::query()->findOrFail($second->id)->type->key);
    }
}
