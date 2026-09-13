<?php

namespace Tests\Feature;

use App\Enums\WorldEntityType;
use App\Enums\WorldEventParticipantRole;
use App\Enums\WorldEventStatus;
use App\Enums\WorldEventType;
use App\Enums\WorldEventVisibility;
use App\Models\Chronicle;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEvent;
use App\Models\WorldRelation;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use App\World\WorldEventService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WorldEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_in_game_timeline_is_not_created(): void
    {
        $this->assertFalse(Schema::hasTable('chronicle_timeline'));
        $this->assertTrue(Schema::hasTable('world_events'));
        $this->assertFalse(Schema::hasColumn('world_events', 'timeline_id'));
    }

    public function test_service_creates_typed_event_with_participants_sources_and_graph_links(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $entities = $this->app->make(WorldEntityService::class);
        $events = $this->app->make(WorldEventService::class);
        $storyteller = User::factory()->storyteller()->create();

        $character = $entities->create($chronicle, WorldEntityType::Character, 'Виктория');
        $location = $entities->create($chronicle, WorldEntityType::Location, 'Прага');
        $identity = $entities->create(
            $chronicle,
            WorldEntityType::Event,
            'Саботаж Маскарада',
            typed: [
                'event_type' => WorldEventType::Political,
                'description' => 'Кто-то нарушил тишину двора.',
                'status' => WorldEventStatus::Proposed,
                'scene_id' => $scene->id,
                'importance' => 4,
                'visibility' => WorldEventVisibility::Public,
            ],
        );

        $event = WorldEvent::query()->findOrFail($identity->id);
        $this->assertSame('Саботаж Маскарада', $event->title);
        $this->assertSame(WorldEventType::Political, $event->event_type);
        $this->assertSame($scene->id, $event->scene_id);
        $this->assertTrue($identity->event->is($event));

        $message = Message::factory()->create(['scene_id' => $scene->id, 'body' => 'Кровь на паркете Элизиума.']);
        $events->addParticipant($event, $character, WorldEventParticipantRole::Actor);
        $events->addParticipant($event, $character, WorldEventParticipantRole::Witness, 'Случайно видела.');
        $events->addSource($event, message: $message, excerpt: 'Кровь на паркете Элизиума.');
        $events->occurredAt($event, $location);
        $followUp = WorldEvent::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Event, 'Паника двора')->id,
        );
        $events->caused($event, $followUp);
        $approved = $events->approve($event, $storyteller);

        $this->assertSame(WorldEventStatus::Canonical, $approved->status);
        $this->assertSame($storyteller->id, $approved->approved_by);
        $this->assertDatabaseCount('world_event_participants', 2);
        $this->assertDatabaseCount('world_event_sources', 1);
        $this->assertTrue(
            WorldRelation::query()
                ->where('source_entity_id', $character->id)
                ->where('target_entity_id', $event->id)
                ->whereHas('type', fn ($query) => $query->where('key', 'participated_in'))
                ->exists(),
        );
        $this->assertTrue(
            WorldRelation::query()
                ->where('source_entity_id', $character->id)
                ->where('target_entity_id', $event->id)
                ->whereHas('type', fn ($query) => $query->where('key', 'witnessed'))
                ->exists(),
        );
        $this->assertTrue(
            WorldRelation::query()
                ->where('source_entity_id', $event->id)
                ->where('target_entity_id', $location->id)
                ->whereHas('type', fn ($query) => $query->where('key', 'occurred_at'))
                ->exists(),
        );
        $this->assertTrue(
            WorldRelation::query()
                ->where('source_entity_id', $event->id)
                ->where('target_entity_id', $followUp->id)
                ->whereHas('type', fn ($query) => $query->where('key', 'caused'))
                ->exists(),
        );
    }

    public function test_participant_from_another_chronicle_is_rejected(): void
    {
        $left = Chronicle::factory()->create();
        $right = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $event = WorldEvent::query()->findOrFail(
            $entities->create($left, WorldEntityType::Event, 'Саботаж')->id,
        );
        $stranger = $entities->create($right, WorldEntityType::Character, 'Маркус');

        $this->expectException(MixedChronicleException::class);
        $this->app->make(WorldEventService::class)
            ->addParticipant($event, $stranger, WorldEventParticipantRole::Mentioned);
    }

    public function test_creating_an_event_without_typed_payload_uses_defaults(): void
    {
        $chronicle = Chronicle::factory()->create();
        $identity = $this->app->make(WorldEntityService::class)
            ->create($chronicle, WorldEntityType::Event, 'Неизвестное происшествие');

        $event = WorldEvent::query()->findOrFail($identity->id);
        $this->assertSame(WorldEventType::Other, $event->event_type);
        $this->assertSame(WorldEventStatus::Proposed, $event->status);
        $this->assertNull($event->scene_id);
    }

    public function test_factory_creates_a_typed_event(): void
    {
        $event = WorldEvent::factory()->create();

        $this->assertSame(WorldEntityType::Event, $event->entity_type);
        $this->assertDatabaseHas('world_entities', [
            'id' => $event->id,
            'entity_type' => WorldEntityType::Event->value,
        ]);
    }
}
