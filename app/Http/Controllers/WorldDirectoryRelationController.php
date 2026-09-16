<?php

namespace App\Http\Controllers;

use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Http\Requests\StoreWorldDirectoryRelationRequest;
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

class WorldDirectoryRelationController extends Controller
{
    /**
     * @var list<string>
     */
    public const DIRECTORY_KEYS = ['controls', 'owns', 'part_of'];

    public function __construct(private WorldRelationService $relations) {}

    public function index(Request $request): JsonResponse
    {
        if (! $request->filled('keys')) {
            abort(422, 'Query parameter keys is required (e.g. controls,owns).');
        }

        $requestedKeys = $this->parseKeys($request->string('keys')->toString());
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );
        $directoryTypes = array_map(
            fn (WorldEntityType $type): string => $type->value,
            WorldEntityService::DIRECTORY_TYPES,
        );
        $typeIds = WorldRelationType::query()->whereIn('key', $requestedKeys)->pluck('id');

        $relations = WorldRelation::query()
            ->active()
            ->where('chronicle_id', $chronicleId)
            ->whereIn('relation_type_id', $typeIds)
            ->whereHas('source', fn ($query) => $query
                ->whereIn('entity_type', $directoryTypes)
                ->where('status', WorldEntityStatus::Active))
            ->whereHas('target', fn ($query) => $query
                ->whereIn('entity_type', $directoryTypes)
                ->where('status', WorldEntityStatus::Active))
            ->with(['source', 'target', 'type'])
            ->orderBy('id')
            ->get();

        return response()->json([
            'relations' => $relations->map(fn (WorldRelation $relation): array => $this->serialize($relation))->values()->all(),
        ]);
    }

    public function store(StoreWorldDirectoryRelationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $chronicleId = Chronicle::resolveId(
            isset($validated['chronicle_id']) ? (int) $validated['chronicle_id'] : null,
        );
        $source = WorldEntity::query()->findOrFail((int) $validated['source_entity_id']);
        $target = WorldEntity::query()->findOrFail((int) $validated['target_entity_id']);
        $this->assertDirectoryEntityInChronicle($source, $chronicleId);
        $this->assertDirectoryEntityInChronicle($target, $chronicleId);

        $type = WorldRelationType::query()->where('key', $validated['relation_key'])->firstOrFail();
        $existing = $this->activeDirectoryBetween($source, $target, $type);

        if ($existing !== null) {
            return response()->json(
                ['relation' => $this->serialize($existing->load(['source', 'target', 'type']))],
                200,
            );
        }

        try {
            $edge = $this->relations->relate($source, $target, $type);
        } catch (WorldRelationException|WorldRelationTypeException $e) {
            abort(422, $e->getMessage());
        } catch (MixedChronicleException $e) {
            abort(409, $e->getMessage());
        }

        return response()->json(
            ['relation' => $this->serialize($edge->load(['source', 'target', 'type']))],
            201,
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

        if (! in_array($worldRelation->type?->key, self::DIRECTORY_KEYS, true)) {
            abort(422, 'Only directory relations (controls, owns, part_of) can be ended here.');
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

    /**
     * @return list<string>
     */
    private function parseKeys(string $raw): array
    {
        $keys = array_values(array_filter(array_map('trim', explode(',', $raw))));

        if ($keys === []) {
            abort(422, 'Query parameter keys is required (e.g. controls,owns).');
        }

        $unknown = array_diff($keys, self::DIRECTORY_KEYS);
        if ($unknown !== []) {
            abort(422, 'Unknown relation keys: '.implode(', ', $unknown).'.');
        }

        return $keys;
    }

    private function assertDirectoryEntityInChronicle(WorldEntity $entity, int $chronicleId): void
    {
        if ((int) $entity->chronicle_id !== $chronicleId) {
            abort(404);
        }

        if (! in_array($entity->entity_type, WorldEntityService::DIRECTORY_TYPES, true)) {
            abort(422, 'Directory relations only link directory entities.');
        }

        if ($entity->status !== WorldEntityStatus::Active) {
            abort(422, 'Archived entities cannot join directory relations.');
        }
    }

    private function activeDirectoryBetween(
        WorldEntity $source,
        WorldEntity $target,
        WorldRelationType $type,
    ): ?WorldRelation {
        return WorldRelation::query()
            ->active()
            ->where('chronicle_id', $source->chronicle_id)
            ->where('relation_type_id', $type->id)
            ->where('source_entity_id', $source->id)
            ->where('target_entity_id', $target->id)
            ->with(['source', 'target', 'type'])
            ->first();
    }
}
