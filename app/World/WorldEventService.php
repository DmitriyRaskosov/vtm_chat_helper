<?php

namespace App\World;

use App\Enums\WorldEntityType;
use App\Enums\WorldEventParticipantRole;
use App\Enums\WorldEventStatus;
use App\Models\CopilotRequest;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use App\Models\WorldEvent;
use App\Models\WorldEventParticipant;
use App\Models\WorldEventSource;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorldEventService
{
    public function __construct(private WorldRelationService $relations) {}

    public function addParticipant(
        WorldEvent $event,
        WorldEntity $entity,
        WorldEventParticipantRole $role,
        ?string $note = null,
    ): WorldEventParticipant {
        if ((int) $event->chronicle_id !== (int) $entity->chronicle_id) {
            throw new MixedChronicleException;
        }

        $note = $note !== null ? trim($note) : null;

        return DB::transaction(function () use ($event, $entity, $role, $note): WorldEventParticipant {
            $participant = WorldEventParticipant::query()->create([
                'event_id' => $event->id,
                'entity_id' => $entity->id,
                'participant_role' => $role,
                'note' => $note === '' ? null : $note,
            ]);

            if ($entity->entity_type === WorldEntityType::Character) {
                $typeKey = $role === WorldEventParticipantRole::Witness ? 'witnessed' : 'participated_in';
                $this->relateIfNew(
                    $entity,
                    WorldEntity::query()->findOrFail($event->id),
                    WorldRelationType::query()->where('key', $typeKey)->firstOrFail(),
                );
            }

            return $participant->refresh();
        });
    }

    public function addSource(
        WorldEvent $event,
        ?Message $message = null,
        ?Scene $scene = null,
        ?CopilotRequest $copilotRequest = null,
        ?string $excerpt = null,
    ): WorldEventSource {
        if ($message === null && $scene === null && $copilotRequest === null) {
            throw new InvalidArgumentException('An event source needs a message, scene, or copilot request.');
        }

        $this->assertSourceChronicle($event, $message, $scene, $copilotRequest);

        $excerpt = $excerpt !== null ? trim($excerpt) : null;

        return WorldEventSource::query()->create([
            'event_id' => $event->id,
            'message_id' => $message?->id,
            'scene_id' => $scene?->id ?? $message?->scene_id ?? $copilotRequest?->scene_id,
            'copilot_request_id' => $copilotRequest?->id,
            'excerpt' => $excerpt === '' ? null : $excerpt,
        ])->refresh();
    }

    public function occurredAt(WorldEvent $event, WorldEntity $location): WorldRelation
    {
        if ($location->entity_type !== WorldEntityType::Location) {
            throw new InvalidArgumentException('occurred_at requires a location.');
        }

        return $this->relations->relate(
            WorldEntity::query()->findOrFail($event->id),
            $location,
            WorldRelationType::query()->where('key', 'occurred_at')->firstOrFail(),
        );
    }

    public function caused(WorldEvent $cause, WorldEvent $effect): WorldRelation
    {
        return $this->relations->relate(
            WorldEntity::query()->findOrFail($cause->id),
            WorldEntity::query()->findOrFail($effect->id),
            WorldRelationType::query()->where('key', 'caused')->firstOrFail(),
        );
    }

    public function approve(WorldEvent $event, User $approver): WorldEvent
    {
        $event->status = WorldEventStatus::Canonical;
        $event->approved_at = now();
        $event->approved_by = $approver->id;
        $event->save();

        return $event->refresh();
    }

    private function relateIfNew(WorldEntity $source, WorldEntity $target, WorldRelationType $type): void
    {
        try {
            $this->relations->relate($source, $target, $type);
        } catch (WorldRelationException) {
            // Active edge of this type already exists.
        }
    }

    private function assertSourceChronicle(
        WorldEvent $event,
        ?Message $message,
        ?Scene $scene,
        ?CopilotRequest $copilotRequest,
    ): void {
        $scene ??= $message?->scene ?? $copilotRequest?->scene;

        if ($scene !== null) {
            $scene->loadMissing('gameSession');

            if ((int) $scene->gameSession->chronicle_id !== (int) $event->chronicle_id) {
                throw new MixedChronicleException;
            }
        }
    }
}
