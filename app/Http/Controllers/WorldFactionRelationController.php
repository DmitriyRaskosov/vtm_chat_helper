<?php

namespace App\Http\Controllers;

use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Http\Requests\StoreWorldFactionRelationRequest;
use App\Models\Chronicle;
use App\Models\WorldEntity;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use App\World\MixedChronicleException;
use App\World\WorldRelationException;
use App\World\WorldRelationService;
use App\World\WorldRelationTypeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WorldFactionRelationController extends Controller
{
    /**
     * @var list<string>
     */
    public const POLITICS_KEYS = ['hostile_to', 'allied_with'];

    public function __construct(private WorldRelationService $relations) {}

    public function index(Request $request): JsonResponse
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );
        $typeIds = WorldRelationType::query()->whereIn('key', self::POLITICS_KEYS)->pluck('id');

        $relations = WorldRelation::query()
            ->active()
            ->where('chronicle_id', $chronicleId)
            ->whereIn('relation_type_id', $typeIds)
            ->whereHas('source', fn ($query) => $query->where('entity_type', WorldEntityType::Faction))
            ->whereHas('target', fn ($query) => $query->where('entity_type', WorldEntityType::Faction))
            ->with(['source', 'target', 'type'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'relations' => $relations->map(fn (WorldRelation $relation): array => $this->serialize($relation))->values()->all(),
        ]);
    }

    public function store(StoreWorldFactionRelationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $chronicleId = Chronicle::resolveId(
            isset($validated['chronicle_id']) ? (int) $validated['chronicle_id'] : null,
        );
        $source = WorldEntity::query()->findOrFail((int) $validated['source_entity_id']);
        $target = WorldEntity::query()->findOrFail((int) $validated['target_entity_id']);
        $this->assertFactionInChronicle($source, $chronicleId);
        $this->assertFactionInChronicle($target, $chronicleId);

        $type = WorldRelationType::query()->where('key', $validated['relation_key'])->firstOrFail();
        $note = isset($validated['note']) && is_string($validated['note']) ? $validated['note'] : null;

        try {
            $existed = $this->activePoliticsBetween($source, $target, $type);
            $edge = $this->relations->replaceAmong(
                $source,
                $target,
                $type,
                self::POLITICS_KEYS,
                $note,
            );
        } catch (WorldRelationException|WorldRelationTypeException|InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        } catch (MixedChronicleException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(
            ['relation' => $this->serialize($edge->load(['source', 'target', 'type']))],
            $existed ? 200 : 201,
        );
    }

    public function end(Request $request, WorldRelation $worldRelation): JsonResponse
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );

        if ((int) $worldRelation->chronicle_id !== $chronicleId) {
            abort(404);
        }

        $worldRelation->load(['type', 'source', 'target']);

        if (! in_array($worldRelation->type?->key, self::POLITICS_KEYS, true)) {
            abort(422, 'Only faction politics relations can be ended here.');
        }

        if (
            $worldRelation->source?->entity_type !== WorldEntityType::Faction
            || $worldRelation->target?->entity_type !== WorldEntityType::Faction
        ) {
            abort(422, 'Only faction politics relations can be ended here.');
        }

        $edge = $this->relations->end($worldRelation);

        return response()->json(['relation' => $this->serialize($edge->load(['source', 'target', 'type']))]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorldRelation $relation): array
    {
        $relation->loadMissing(['source', 'target', 'type']);

        return [
            'id' => (int) $relation->id,
            'relation_key' => $relation->type?->key,
            'source_entity_id' => (int) $relation->source_entity_id,
            'source_name' => $relation->source?->canonical_name,
            'target_entity_id' => (int) $relation->target_entity_id,
            'target_name' => $relation->target?->canonical_name,
            'note' => $relation->note,
            'ended_at' => $relation->ended_at?->toIso8601String(),
        ];
    }

    private function assertFactionInChronicle(WorldEntity $entity, int $chronicleId): void
    {
        if ((int) $entity->chronicle_id !== $chronicleId) {
            abort(404);
        }

        if ($entity->entity_type !== WorldEntityType::Faction) {
            abort(422, 'Faction politics only link factions.');
        }

        if ($entity->status !== WorldEntityStatus::Active) {
            abort(422, 'Archived factions cannot join politics.');
        }
    }

    private function activePoliticsBetween(WorldEntity $source, WorldEntity $target, WorldRelationType $type): bool
    {
        return WorldRelation::query()
            ->active()
            ->where('chronicle_id', $source->chronicle_id)
            ->where('relation_type_id', $type->id)
            ->where(function ($query) use ($source, $target): void {
                $query->where(function ($inner) use ($source, $target): void {
                    $inner->where('source_entity_id', $source->id)
                        ->where('target_entity_id', $target->id);
                })->orWhere(function ($inner) use ($source, $target): void {
                    $inner->where('source_entity_id', $target->id)
                        ->where('target_entity_id', $source->id);
                });
            })
            ->exists();
    }
}
