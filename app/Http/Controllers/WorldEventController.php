<?php

namespace App\Http\Controllers;

use App\Enums\WorldEntityType;
use App\Enums\WorldEventStatus;
use App\Enums\WorldEventType;
use App\Enums\WorldEventVisibility;
use App\Http\Requests\StoreWorldEventParticipantRequest;
use App\Http\Requests\StoreWorldEventRequest;
use App\Http\Requests\StoreWorldEventSourceRequest;
use App\Models\Chronicle;
use App\Models\Message;
use App\Models\Scene;
use App\Models\WorldEntity;
use App\Models\WorldEvent;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use App\World\WorldEventService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use InvalidArgumentException;

class WorldEventController extends Controller
{
    public function __construct(
        private WorldEntityService $entities,
        private WorldEventService $events,
    ) {}

    public function store(StoreWorldEventRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $chronicle = Chronicle::query()->findOrFail(
            Chronicle::resolveId(isset($validated['chronicle_id']) ? (int) $validated['chronicle_id'] : null),
        );

        $description = trim((string) ($validated['description'] ?? $validated['summary'] ?? ''));
        if ($description === '') {
            $description = null;
        }

        $sceneId = isset($validated['scene_id']) ? (int) $validated['scene_id'] : null;
        if ($sceneId !== null) {
            $scene = Scene::query()->with('gameSession')->findOrFail($sceneId);
            if ((int) $scene->gameSession->chronicle_id !== (int) $chronicle->id) {
                abort(404);
            }
        }

        $eventType = isset($validated['event_type'])
            ? WorldEventType::from($validated['event_type'])
            : WorldEventType::Other;
        $visibility = isset($validated['visibility'])
            ? WorldEventVisibility::from($validated['visibility'])
            : WorldEventVisibility::Public;

        try {
            $entity = $this->entities->create(
                $chronicle,
                WorldEntityType::Event,
                $validated['title'],
                shortDescription: $description,
                typed: [
                    'event_type' => $eventType,
                    'description' => $description,
                    'status' => WorldEventStatus::Proposed,
                    'scene_id' => $sceneId,
                    'importance' => (int) ($validated['importance'] ?? 0),
                    'visibility' => $visibility,
                ],
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        } catch (MixedChronicleException $e) {
            abort(404, $e->getMessage());
        }

        $event = WorldEvent::query()->findOrFail($entity->id);

        return response()->json(['event' => $this->serialize($event)], 201);
    }

    public function storeParticipant(
        StoreWorldEventParticipantRequest $request,
        WorldEvent $event,
    ): JsonResponse {
        $this->assertEventChronicle($request, $event);

        $entity = WorldEntity::query()->findOrFail((int) $request->validated('entity_id'));
        $role = \App\Enums\WorldEventParticipantRole::from((string) $request->validated('role'));

        try {
            $participant = $this->events->addParticipant($event, $entity, $role);
        } catch (MixedChronicleException $e) {
            abort(404, $e->getMessage());
        }

        return response()->json([
            'participant' => [
                'id' => (int) $participant->id,
                'event_id' => (int) $participant->event_id,
                'entity_id' => (int) $participant->entity_id,
                'role' => $participant->participant_role->value,
            ],
        ], 201);
    }

    public function storeSource(
        StoreWorldEventSourceRequest $request,
        WorldEvent $event,
    ): JsonResponse {
        $this->assertEventChronicle($request, $event);

        $message = $request->filled('message_id')
            ? Message::query()->findOrFail((int) $request->validated('message_id'))
            : null;
        $scene = $request->filled('scene_id')
            ? Scene::query()->with('gameSession')->findOrFail((int) $request->validated('scene_id'))
            : null;

        if ($scene !== null && (int) $scene->gameSession->chronicle_id !== (int) $event->chronicle_id) {
            abort(404);
        }

        if ($message !== null) {
            $message->loadMissing('scene.gameSession');
            if ((int) $message->scene->gameSession->chronicle_id !== (int) $event->chronicle_id) {
                abort(404);
            }
        }

        $excerpt = $message !== null ? Str::limit((string) $message->body, 500) : null;

        try {
            $source = $this->events->addSource($event, $message, $scene, excerpt: $excerpt);
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        } catch (MixedChronicleException $e) {
            abort(404, $e->getMessage());
        }

        return response()->json([
            'source' => [
                'id' => (int) $source->id,
                'event_id' => (int) $source->event_id,
                'message_id' => $source->message_id !== null ? (int) $source->message_id : null,
                'scene_id' => $source->scene_id !== null ? (int) $source->scene_id : null,
                'excerpt' => $source->excerpt,
            ],
        ], 201);
    }

    private function assertEventChronicle(StoreWorldEventParticipantRequest|StoreWorldEventSourceRequest $request, WorldEvent $event): void
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );

        if ((int) $event->chronicle_id !== $chronicleId) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorldEvent $event): array
    {
        return [
            'id' => (int) $event->id,
            'chronicle_id' => (int) $event->chronicle_id,
            'title' => $event->title,
            'description' => $event->description,
            'event_type' => $event->event_type->value,
            'status' => $event->status->value,
            'scene_id' => $event->scene_id !== null ? (int) $event->scene_id : null,
            'importance' => (int) $event->importance,
            'visibility' => $event->visibility->value,
        ];
    }
}
