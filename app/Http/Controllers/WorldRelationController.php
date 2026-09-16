<?php

namespace App\Http\Controllers;

use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Http\Requests\StoreWorldRelationRequest;
use App\Models\Chronicle;
use App\Models\WorldEntity;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use App\World\WorldRelationException;
use App\World\WorldRelationService;
use App\World\WorldRelationTypeException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WorldRelationController extends Controller
{
    /**
     * @var list<string>
     */
    public const POLITICS_KEYS = ['hostile_to', 'allied_with'];

    /**
     * @var list<string>
     */
    public const DIRECTORY_KEYS = ['controls', 'owns', 'part_of'];

    public function __construct(private WorldRelationService $relations) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->filled('keys')) {
            abort(422, 'Query parameter keys is required (e.g. controls,owns,hostile_to).');
        }

        $requestedKeys = $this->parseKeys($request->string('keys')->toString());
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );

        $relations = WorldRelation::query()
            ->active()
            ->where('chronicle_id', $chronicleId)
            ->whereIn('relation', $requestedKeys)
            ->with(['source', 'target'])
            ->orderBy('id')
            ->get()
            ->filter(fn (WorldRelation $relation): bool => $this->passesIndexFilters($relation, $requestedKeys))
            ->values();

        return response()->json([
            'relations' => $relations->map(fn (WorldRelation $relation): array => $this->serialize($relation))->all(),
        ]);
    }

    public function store(StoreWorldRelationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $chronicleId = Chronicle::resolveId(
            isset($validated['chronicle_id']) ? (int) $validated['chronicle_id'] : null,
        );
        $source = WorldEntity::query()->findOrFail((int) $validated['source_entity_id']);
        $target = WorldEntity::query()->findOrFail((int) $validated['target_entity_id']);
        $relationKey = (string) $validated['relation_key'];
        $note = isset($validated['note']) && is_string($validated['note']) ? $validated['note'] : null;
        $intensity = array_key_exists('intensity', $validated) ? $validated['intensity'] : null;
        $metadata = isset($validated['metadata']) && is_array($validated['metadata']) ? $validated['metadata'] : null;

        $this->assertEntitiesInChronicle($source, $target, $chronicleId);
        $this->assertStorePolicy($source, $target, $relationKey);

        $type = WorldRelationType::query()->where('key', $relationKey)->firstOrFail();

        try {
            if (in_array($relationKey, self::POLITICS_KEYS, true)) {
                $existed = $this->activePoliticsBetween($source, $target, $type);
                $edge = $this->relations->replaceAmong(
                    $source,
                    $target,
                    $type,
                    self::POLITICS_KEYS,
                    $note,
                );
            } else {
                $existing = $this->activeDirectedBetween($source, $target, $relationKey);
                if ($existing !== null) {
                    return response()->json(
                        ['relation' => $this->serialize($existing->load(['source', 'target']))],
                        200,
                    );
                }

                $existed = false;
                $edge = $this->relations->relate(
                    $source,
                    $target,
                    $type,
                    note: $note,
                    metadata: $metadata,
                    intensity: is_int($intensity) ? $intensity : null,
                );
            }
        } catch (WorldRelationException|WorldRelationTypeException|InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        } catch (MixedChronicleException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(
            ['relation' => $this->serialize($edge->load(['source', 'target']))],
            ($existed ?? false) ? 200 : 201,
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

        $edge = $this->relations->end($worldRelation);

        return response()->json(['relation' => $this->serialize($edge->load(['source', 'target']))]);
    }

    public function neighbors(Request $request, WorldEntity $worldEntity): JsonResponse
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );

        if ((int) $worldEntity->chronicle_id !== $chronicleId) {
            abort(404);
        }

        $relationKey = $request->filled('relation') ? $request->string('relation')->toString() : null;
        $neighbors = $this->relations->neighbors($worldEntity, $relationKey);

        return response()->json([
            'relations' => $neighbors->map(fn (WorldRelation $relation): array => $this->serialize($relation))->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorldRelation $relation): array
    {
        $relation->loadMissing(['source', 'target']);

        return [
            'id' => (int) $relation->id,
            'relation' => $relation->relation,
            'relation_key' => $relation->relation,
            'source_type' => $relation->source_type,
            'source_id' => (int) $relation->source_id,
            'source_entity_id' => (int) $relation->source_id,
            'source_name' => $relation->source?->canonical_name,
            'target_type' => $relation->target_type,
            'target_id' => (int) $relation->target_id,
            'target_entity_id' => (int) $relation->target_id,
            'target_name' => $relation->target?->canonical_name,
            'intensity' => $relation->intensity,
            'metadata' => $relation->metadata,
            'note' => $relation->note,
            'weight' => (float) $relation->weight,
            'valid_from' => $relation->valid_from?->toIso8601String(),
            'valid_to' => $relation->valid_to?->toIso8601String(),
            'ended_at' => $relation->valid_to?->toIso8601String(),
        ];
    }

    /**
     * @param  list<string>  $requestedKeys
     */
    private function passesIndexFilters(WorldRelation $relation, array $requestedKeys): bool
    {
        $politicsOnly = array_intersect($requestedKeys, self::POLITICS_KEYS) !== [];
        $directoryOnly = array_intersect($requestedKeys, self::DIRECTORY_KEYS) !== []
            && ! $politicsOnly;

        if ($politicsOnly && count($requestedKeys) <= count(self::POLITICS_KEYS)) {
            return $relation->source?->entity_type === WorldEntityType::Faction
                && $relation->target?->entity_type === WorldEntityType::Faction
                && $relation->source?->status === WorldEntityStatus::Active
                && $relation->target?->status === WorldEntityStatus::Active;
        }

        if ($directoryOnly || array_intersect($requestedKeys, self::DIRECTORY_KEYS) !== []) {
            $directoryTypes = WorldEntityService::DIRECTORY_TYPES;

            return in_array($relation->source?->entity_type, $directoryTypes, true)
                && in_array($relation->target?->entity_type, $directoryTypes, true)
                && $relation->source?->status === WorldEntityStatus::Active
                && $relation->target?->status === WorldEntityStatus::Active;
        }

        return true;
    }

    private function assertStorePolicy(WorldEntity $source, WorldEntity $target, string $relationKey): void
    {
        if (in_array($relationKey, self::POLITICS_KEYS, true)) {
            $this->assertFactionInChronicle($source);
            $this->assertFactionInChronicle($target);

            return;
        }

        if (in_array($relationKey, self::POLITICS_KEYS, true) === false
            && in_array($relationKey, self::DIRECTORY_KEYS, true)) {
            $this->assertDirectoryEntity($source);
            $this->assertDirectoryEntity($target);
        }
    }

    private function assertEntitiesInChronicle(WorldEntity $source, WorldEntity $target, int $chronicleId): void
    {
        if ((int) $source->chronicle_id !== $chronicleId || (int) $target->chronicle_id !== $chronicleId) {
            abort(404);
        }
    }

    private function assertFactionInChronicle(WorldEntity $entity): void
    {
        if ($entity->entity_type !== WorldEntityType::Faction) {
            abort(422, 'Faction politics only link factions.');
        }

        if ($entity->status !== WorldEntityStatus::Active) {
            abort(422, 'Archived factions cannot join politics.');
        }
    }

    private function assertDirectoryEntity(WorldEntity $entity): void
    {
        if (! in_array($entity->entity_type, WorldEntityService::DIRECTORY_TYPES, true)) {
            abort(422, 'Directory relations only link directory entities.');
        }

        if ($entity->status !== WorldEntityStatus::Active) {
            abort(422, 'Archived entities cannot join directory relations.');
        }
    }

    /**
     * @return list<string>
     */
    private function parseKeys(string $raw): array
    {
        $keys = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if ($keys === []) {
            abort(422, 'Query parameter keys is required.');
        }

        $allowed = array_map(
            fn (array $definition): string => $definition['key'],
            \App\World\WorldRelationTypeCatalog::definitions(),
        );
        $unknown = array_diff($keys, $allowed);
        if ($unknown !== []) {
            abort(422, 'Unknown relation keys: '.implode(', ', $unknown).'.');
        }

        return $keys;
    }

    private function activePoliticsBetween(WorldEntity $source, WorldEntity $target, WorldRelationType $type): bool
    {
        return WorldRelation::query()
            ->active()
            ->where('chronicle_id', $source->chronicle_id)
            ->where('relation', $type->key)
            ->where(function ($query) use ($source, $target): void {
                $query->where(function ($inner) use ($source, $target): void {
                    $inner->where('source_id', $source->id)->where('target_id', $target->id);
                })->orWhere(function ($inner) use ($source, $target): void {
                    $inner->where('source_id', $target->id)->where('target_id', $source->id);
                });
            })
            ->exists();
    }

    private function activeDirectedBetween(
        WorldEntity $source,
        WorldEntity $target,
        string $relationKey,
    ): ?WorldRelation {
        return WorldRelation::query()
            ->active()
            ->where('chronicle_id', $source->chronicle_id)
            ->where('relation', $relationKey)
            ->where('source_id', $source->id)
            ->where('target_id', $target->id)
            ->with(['source', 'target'])
            ->first();
    }
}
