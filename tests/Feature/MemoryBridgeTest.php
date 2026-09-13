<?php

namespace Tests\Feature;

use App\Enums\CharacterMemoryNodeType;
use App\Enums\LoreAccessLevel;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Enums\MemoryEntityRole;
use App\Enums\WorldEntityType;
use App\Enums\WorldEventVisibility;
use App\Lore\LoreEntryService;
use App\Memory\CharacterMemoryService;
use App\Memory\MemoryBridgeService;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\Message;
use App\Models\Scene;
use App\Models\WorldEntity;
use App\Models\WorldEvent;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MemoryBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_same_chronicle_bridges_do_not_copy_memory_text_onto_canon(): void
    {
        $scene = Scene::query()->active()->firstOrFail();
        $chronicle = $scene->gameSession->chronicle;
        $entities = $this->app->make(WorldEntityService::class);
        $character = $this->npcIn($chronicle, 'Виктория');
        $prince = $entities->create($chronicle, WorldEntityType::Character, 'Князь', 'Правит двором.');
        $node = $this->app->make(CharacterMemoryService::class)->remember(
            $character,
            'Князь уже мёртв.',
            CharacterMemoryNodeType::Conclusion,
            isFalseBelief: true,
        );
        $bridges = $this->app->make(MemoryBridgeService::class);
        $lore = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Двор',
            'canonical_text' => 'Князь жив и правит Прагой.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');
        $event = WorldEvent::query()->findOrFail(
            $entities->create($chronicle, WorldEntityType::Event, 'Приём в опере')->id,
        );
        $message = Message::factory()->create([
            'scene_id' => $scene->id,
            'body' => 'Князь кивнул с балкона.',
        ]);

        $bridges->linkEntity($node, $prince, MemoryEntityRole::Subject);
        $bridges->linkLore($node, $lore);
        $bridges->linkEvent($node, $event);
        $bridges->linkMessage($node, $message);
        $bridges->linkScene($node, $scene);

        $this->assertTrue($bridges->worldEntitiesFor($node)->contains('id', $prince->id));
        $this->assertTrue($bridges->loreEntriesFor($node)->contains('id', $lore->id));
        $this->assertTrue($bridges->eventsFor($node)->contains('id', $event->id));
        $this->assertTrue($bridges->messagesFor($node)->contains('id', $message->id));
        $this->assertTrue($bridges->scenesFor($node)->contains('id', $scene->id));
        $this->assertSame('Правит двором.', $prince->fresh()->short_description);
        $this->assertSame('Князь жив и правит Прагой.', $lore->fresh()->canonical_text);
        $this->assertNotEquals($node->node_text, $prince->fresh()->short_description);
        $this->assertDatabaseCount('memory_node_entities', 1);
        $this->assertDatabaseCount('memory_node_lore_entries', 1);
        $this->assertDatabaseCount('memory_node_events', 1);
        $this->assertDatabaseCount('memory_node_messages', 1);
        $this->assertDatabaseCount('memory_node_scenes', 1);
    }

    public function test_mixed_chronicle_bridges_are_rejected(): void
    {
        $home = Chronicle::factory()->create();
        $other = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $character = $this->npcIn($home);
        $node = $this->app->make(CharacterMemoryService::class)
            ->remember($character, 'Чужой двор.', CharacterMemoryNodeType::Place);
        $foreignEntity = $entities->create($other, WorldEntityType::Location, 'Берлин');
        $bridges = $this->app->make(MemoryBridgeService::class);

        try {
            $bridges->linkEntity($node, $foreignEntity);
            $this->fail('Expected mixed chronicle rejection.');
        } catch (MixedChronicleException) {
            $this->assertDatabaseCount('memory_node_entities', 0);
        }

        $foreignLore = $this->app->make(LoreEntryService::class)->publish($other, [
            'title' => 'Чужой лор',
            'canonical_text' => 'Это не та хроника.',
            'status' => LoreEntryStatus::Approved,
        ], 'v1');

        $this->expectException(MixedChronicleException::class);
        $bridges->linkLore($node, $foreignLore);
    }

    public function test_memory_without_bridges_does_not_seed_world_entities(): void
    {
        $chronicle = Chronicle::factory()->create();
        $entities = $this->app->make(WorldEntityService::class);
        $character = $this->npcIn($chronicle, 'Виктория');
        $entities->create($chronicle, WorldEntityType::Location, 'Пражский Элизиум');
        $node = $this->app->make(CharacterMemoryService::class)->remember(
            $character,
            'Двор собирался в Пражском Элизиуме.',
            CharacterMemoryNodeType::Place,
            aliases: ['Пражский Элизиум'],
        );

        $linked = $this->app->make(MemoryBridgeService::class)->worldEntitiesFor($node);

        $this->assertTrue($linked->isEmpty());
        $this->assertDatabaseCount('memory_node_entities', 0);
        $this->assertTrue(
            WorldEntity::query()->where('canonical_name', 'Пражский Элизиум')->exists(),
        );
    }

    public function test_ungranted_lore_bridge_is_hidden_from_npc_and_does_not_leak_canon_text(): void
    {
        $chronicle = Chronicle::factory()->create();
        $character = $this->npcIn($chronicle);
        $node = $this->app->make(CharacterMemoryService::class)->remember(
            $character,
            'Сир служит Шабашу.',
            CharacterMemoryNodeType::Rumor,
        );
        $secret = $this->app->make(LoreEntryService::class)->publish($chronicle, [
            'title' => 'Тайна сира',
            'canonical_text' => 'Сир Виктории служит Шабашу.',
            'status' => LoreEntryStatus::Approved,
            'visibility' => LoreVisibility::StorytellerOnly,
            'classification' => LoreAccessLevel::L5,
        ], 'v1');
        $event = WorldEvent::query()->findOrFail(
            $this->app->make(WorldEntityService::class)->create(
                $chronicle,
                WorldEntityType::Event,
                'Тайная аудиенция',
                typed: ['visibility' => WorldEventVisibility::StorytellerOnly],
            )->id,
        );
        $bridges = $this->app->make(MemoryBridgeService::class);
        $bridges->linkLore($node, $secret);
        $bridges->linkEvent($node, $event);

        $this->assertTrue($bridges->loreEntriesFor($node, forNpc: true)->isEmpty());
        $this->assertTrue($bridges->loreEntriesFor($node, forNpc: false)->contains('id', $secret->id));
        $this->assertTrue($bridges->eventsFor($node, forNpc: true)->isEmpty());
        $this->assertTrue($bridges->eventsFor($node, forNpc: false)->contains('id', $event->id));
        $this->assertSame('Сир Виктории служит Шабашу.', $secret->fresh()->canonical_text);
        $this->assertNotEquals($node->node_text, $secret->fresh()->canonical_text);
    }

    private function npcIn(Chronicle $chronicle, string $name = 'Виктория'): Character
    {
        return Character::query()->findOrFail(
            $this->app->make(WorldEntityService::class)
                ->create($chronicle, WorldEntityType::Character, $name.'-'.uniqid())
                ->id,
        );
    }
}
