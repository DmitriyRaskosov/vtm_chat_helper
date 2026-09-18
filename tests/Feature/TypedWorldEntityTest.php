<?php

namespace Tests\Feature;

use App\Enums\CharacterType;
use App\Enums\FactionStatus;
use App\Enums\ItemStatus;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\Concept;
use App\Models\Coterie;
use App\Models\Faction;
use App\Models\Item;
use App\Models\Location;
use App\Models\WorldEntity;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TypedWorldEntityTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_creates_typed_rows_with_shared_primary_keys(): void
    {
        $chronicle = Chronicle::factory()->create();
        $service = $this->app->make(WorldEntityService::class);

        $city = $service->create(
            $chronicle,
            WorldEntityType::Location,
            'Прага',
            typed: [
                'details' => ['region' => 'Bohemia'],
            ],
        );
        $elisium = $service->create(
            $chronicle,
            WorldEntityType::Location,
            'Элизиум',
            typed: [
                'parent_location_id' => $city->id,
                'details' => ['atmosphere' => 'court'],
            ],
        );
        $camarilla = $service->create(
            $chronicle,
            WorldEntityType::Faction,
            'Камарилья',
            typed: [
                'status' => FactionStatus::Covert,
            ],
        );
        $coterie = $service->create(
            $chronicle,
            WorldEntityType::Coterie,
            'Круг Влтавы',
            typed: [
                'sect_faction_id' => $camarilla->id,
            ],
        );
        $blade = $service->create(
            $chronicle,
            WorldEntityType::Item,
            'Клинок',
            typed: [
                'status' => ItemStatus::Held,
                'owner_entity_id' => $coterie->id,
            ],
        );
        $masquerade = $service->create(
            $chronicle,
            WorldEntityType::Concept,
            'Маскарад',
            'Скрывать существование вампиров.',
            typed: [
                'definition' => 'Не раскрывать природу каинитов смертным.',
            ],
        );

        $this->assertSame($city->id, $city->location->id);
        $this->assertSame(['region' => 'Bohemia'], $city->location->details);
        $this->assertSame(['region' => 'Bohemia'], $elisium->location->parent->details);
        $this->assertSame($city->id, $elisium->location->parent_location_id);

        $this->assertSame(FactionStatus::Covert, $camarilla->faction->status);
        $this->assertSame($camarilla->id, $coterie->coterie->sect_faction_id);

        $this->assertSame($coterie->id, $blade->item->owner_entity_id);
        $this->assertSame(ItemStatus::Held, $blade->item->status);

        $this->assertSame('Не раскрывать природу каинитов смертным.', $masquerade->concept->definition);

        $this->assertTrue(Location::query()->whereKey($elisium->id)->exists());
        $this->assertTrue(Coterie::query()->whereKey($coterie->id)->exists());
        $this->assertTrue(Item::query()->whereKey($blade->id)->exists());
        $this->assertTrue(Concept::query()->whereKey($masquerade->id)->exists());
    }

    public function test_typed_row_must_match_world_entity_type(): void
    {
        $faction = $this->app->make(WorldEntityService::class)->create(
            Chronicle::factory()->create(),
            WorldEntityType::Faction,
            'Камарилья',
        );

        $this->expectException(QueryException::class);

        Location::query()->create([
            'id' => $faction->id,
            'chronicle_id' => $faction->chronicle_id,
            'entity_type' => WorldEntityType::Location,
        ]);
    }

    public function test_parent_location_must_belong_to_the_same_chronicle(): void
    {
        $service = $this->app->make(WorldEntityService::class);
        $prague = $service->create(
            Chronicle::factory()->create(),
            WorldEntityType::Location,
            'Прага',
        );

        $this->expectException(MixedChronicleException::class);

        $service->create(
            Chronicle::factory()->create(),
            WorldEntityType::Location,
            'Элизиум',
            typed: ['parent_location_id' => $prague->id],
        );
    }

    public function test_item_owner_must_belong_to_the_same_chronicle(): void
    {
        $service = $this->app->make(WorldEntityService::class);
        $owner = $service->create(
            Chronicle::factory()->create(),
            WorldEntityType::Faction,
            'Камарилья',
        );

        $this->expectException(MixedChronicleException::class);

        $service->create(
            Chronicle::factory()->create(),
            WorldEntityType::Item,
            'Клинок',
            typed: ['owner_entity_id' => $owner->id],
        );
    }

    public function test_factories_share_primary_keys_with_world_entities(): void
    {
        $location = Location::factory()->create();
        $faction = Faction::factory()->create();
        $item = Item::factory()->create();
        $concept = Concept::factory()->create();
        $character = Character::factory()->create();

        $this->assertSame(WorldEntityType::Location, WorldEntity::query()->findOrFail($location->id)->entity_type);
        $this->assertSame(WorldEntityType::Faction, WorldEntity::query()->findOrFail($faction->id)->entity_type);
        $this->assertSame(WorldEntityType::Item, WorldEntity::query()->findOrFail($item->id)->entity_type);
        $this->assertSame(WorldEntityType::Concept, WorldEntity::query()->findOrFail($concept->id)->entity_type);
        $this->assertSame(WorldEntityType::Character, WorldEntity::query()->findOrFail($character->id)->entity_type);
        $this->assertTrue(WorldEntity::query()->whereKey($location->id)->exists());
    }

    public function test_character_identity_creates_typed_row(): void
    {
        $entity = $this->app->make(WorldEntityService::class)->create(
            Chronicle::factory()->create(),
            WorldEntityType::Character,
            'Виктория',
        );

        $this->assertNotNull($entity->character);
        $this->assertSame($entity->id, $entity->character->id);
        $this->assertSame(CharacterType::Npc, $entity->character->character_type);
        $this->assertNull($entity->character->user_id);
        $this->assertTrue($entity->character->is_active);
        $this->assertNull($entity->location);
        $this->assertNull($entity->faction);
        $this->assertNull($entity->clan);
        $this->assertNull($entity->coterie);
        $this->assertNull($entity->circle);
        $this->assertNull($entity->item);
        $this->assertNull($entity->concept);
    }
}
