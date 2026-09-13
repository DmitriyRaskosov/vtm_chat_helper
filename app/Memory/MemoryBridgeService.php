<?php

namespace App\Memory;

use App\Character\CharacterLoreKnowledgeService;
use App\Enums\MemoryEntityRole;
use App\Enums\WorldEventVisibility;
use App\Models\CharacterMemoryNode;
use App\Models\LoreEntry;
use App\Models\MemoryNodeEntity;
use App\Models\MemoryNodeEvent;
use App\Models\MemoryNodeLoreEntry;
use App\Models\MemoryNodeMessage;
use App\Models\MemoryNodeScene;
use App\Models\Message;
use App\Models\Scene;
use App\Models\WorldEntity;
use App\Models\WorldEvent;
use App\World\MixedChronicleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MemoryBridgeService
{
    public function __construct(private CharacterLoreKnowledgeService $knowledge) {}

    public function linkEntity(
        CharacterMemoryNode $node,
        WorldEntity $entity,
        MemoryEntityRole $role = MemoryEntityRole::Subject,
    ): MemoryNodeEntity {
        $this->assertSameChronicle($node, (int) $entity->chronicle_id);

        return DB::transaction(function () use ($node, $entity, $role): MemoryNodeEntity {
            $existing = MemoryNodeEntity::query()
                ->where('memory_node_id', $node->id)
                ->where('entity_id', $entity->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->role = $role;
                $existing->save();

                return $existing->refresh();
            }

            return MemoryNodeEntity::query()->create([
                'memory_node_id' => $node->id,
                'character_id' => $node->character_id,
                'entity_id' => $entity->id,
                'chronicle_id' => $entity->chronicle_id,
                'role' => $role,
            ]);
        });
    }

    public function linkMessage(CharacterMemoryNode $node, Message $message): MemoryNodeMessage
    {
        $message->loadMissing('scene.gameSession');
        $this->assertSameChronicle($node, (int) $message->scene->gameSession->chronicle_id);

        return DB::transaction(function () use ($node, $message): MemoryNodeMessage {
            $existing = MemoryNodeMessage::query()
                ->where('memory_node_id', $node->id)
                ->where('message_id', $message->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return MemoryNodeMessage::query()->create([
                'memory_node_id' => $node->id,
                'character_id' => $node->character_id,
                'message_id' => $message->id,
            ]);
        });
    }

    public function linkEvent(CharacterMemoryNode $node, WorldEvent $event): MemoryNodeEvent
    {
        $this->assertSameChronicle($node, (int) $event->chronicle_id);

        return DB::transaction(function () use ($node, $event): MemoryNodeEvent {
            $existing = MemoryNodeEvent::query()
                ->where('memory_node_id', $node->id)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return MemoryNodeEvent::query()->create([
                'memory_node_id' => $node->id,
                'character_id' => $node->character_id,
                'event_id' => $event->id,
                'chronicle_id' => $event->chronicle_id,
            ]);
        });
    }

    public function linkLore(CharacterMemoryNode $node, LoreEntry $entry): MemoryNodeLoreEntry
    {
        $this->assertSameChronicle($node, (int) $entry->chronicle_id);

        return DB::transaction(function () use ($node, $entry): MemoryNodeLoreEntry {
            $existing = MemoryNodeLoreEntry::query()
                ->where('memory_node_id', $node->id)
                ->where('lore_entry_id', $entry->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return MemoryNodeLoreEntry::query()->create([
                'memory_node_id' => $node->id,
                'character_id' => $node->character_id,
                'lore_entry_id' => $entry->id,
                'chronicle_id' => $entry->chronicle_id,
            ]);
        });
    }

    public function linkScene(CharacterMemoryNode $node, Scene $scene): MemoryNodeScene
    {
        $scene->loadMissing('gameSession');
        $this->assertSameChronicle($node, (int) $scene->gameSession->chronicle_id);

        return DB::transaction(function () use ($node, $scene): MemoryNodeScene {
            $existing = MemoryNodeScene::query()
                ->where('memory_node_id', $node->id)
                ->where('scene_id', $scene->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            return MemoryNodeScene::query()->create([
                'memory_node_id' => $node->id,
                'character_id' => $node->character_id,
                'scene_id' => $scene->id,
            ]);
        });
    }

    /**
     * World identities this memory is about. Never inferred from node text.
     *
     * @return Collection<int, WorldEntity>
     */
    public function worldEntitiesFor(CharacterMemoryNode $node): Collection
    {
        $ids = MemoryNodeEntity::query()
            ->where('memory_node_id', $node->id)
            ->pluck('entity_id');

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return WorldEntity::query()->whereIn('id', $ids)->orderBy('id')->get();
    }

    /**
     * @return Collection<int, Message>
     */
    public function messagesFor(CharacterMemoryNode $node): Collection
    {
        $ids = MemoryNodeMessage::query()
            ->where('memory_node_id', $node->id)
            ->pluck('message_id');

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return Message::query()->whereIn('id', $ids)->orderBy('id')->get();
    }

    /**
     * @return Collection<int, WorldEvent>
     */
    public function eventsFor(CharacterMemoryNode $node, bool $forNpc = false): Collection
    {
        $ids = MemoryNodeEvent::query()
            ->where('memory_node_id', $node->id)
            ->pluck('event_id');

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return WorldEvent::query()
            ->whereIn('id', $ids)
            ->when(
                $forNpc,
                fn ($query) => $query->where('visibility', WorldEventVisibility::Public),
            )
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, LoreEntry>
     */
    public function loreEntriesFor(CharacterMemoryNode $node, bool $forNpc = false): Collection
    {
        $ids = MemoryNodeLoreEntry::query()
            ->where('memory_node_id', $node->id)
            ->pluck('lore_entry_id');

        if ($ids->isEmpty()) {
            return new Collection;
        }

        $known = $forNpc
            ? $this->knowledge->knownLoreEntryIds($node->character)
            : null;

        if ($known !== null && $known === []) {
            return new Collection;
        }

        return LoreEntry::query()
            ->whereIn('id', $ids)
            ->when($known !== null, fn ($query) => $query->whereIn('id', $known))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Scene>
     */
    public function scenesFor(CharacterMemoryNode $node): Collection
    {
        $ids = MemoryNodeScene::query()
            ->where('memory_node_id', $node->id)
            ->pluck('scene_id');

        if ($ids->isEmpty()) {
            return new Collection;
        }

        return Scene::query()->whereIn('id', $ids)->orderBy('id')->get();
    }

    private function assertSameChronicle(CharacterMemoryNode $node, int $chronicleId): void
    {
        $node->loadMissing('character');

        if ((int) $node->character->chronicle_id !== $chronicleId) {
            throw new MixedChronicleException;
        }
    }
}
