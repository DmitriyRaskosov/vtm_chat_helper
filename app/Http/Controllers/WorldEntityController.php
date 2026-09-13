<?php

namespace App\Http\Controllers;

use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Http\Requests\StoreWorldEntityRequest;
use App\Http\Requests\UpdateWorldEntityRequest;
use App\Models\Chronicle;
use App\Models\WorldEntity;
use App\World\MixedChronicleException;
use App\World\WorldEntityService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class WorldEntityController extends Controller
{
    /**
     * @var list<WorldEntityType>
     */
    public const DIRECTORY_TYPES = [
        WorldEntityType::Faction,
        WorldEntityType::Location,
        WorldEntityType::Item,
        WorldEntityType::Concept,
    ];

    public function __construct(private WorldEntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );
        $types = array_map(fn (WorldEntityType $type): string => $type->value, self::DIRECTORY_TYPES);

        $query = WorldEntity::query()
            ->where('chronicle_id', $chronicleId)
            ->whereIn('entity_type', $types)
            ->with(['faction', 'location', 'item', 'concept'])
            ->orderBy('entity_type')
            ->orderBy('canonical_name')
            ->orderBy('id');

        $active = (clone $query)->where('status', WorldEntityStatus::Active)->get();
        $archived = (clone $query)->where('status', WorldEntityStatus::Archived)->get();

        return response()->json([
            'entities' => $active->map(fn (WorldEntity $entity): array => $this->serialize($entity))->values()->all(),
            'archived' => $archived->map(fn (WorldEntity $entity): array => $this->serialize($entity))->values()->all(),
        ]);
    }

    public function store(StoreWorldEntityRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $chronicle = Chronicle::query()->findOrFail(
            Chronicle::resolveId(isset($validated['chronicle_id']) ? (int) $validated['chronicle_id'] : null),
        );
        $type = WorldEntityType::from($validated['entity_type']);
        $description = isset($validated['short_description'])
            ? (is_string($validated['short_description']) ? trim($validated['short_description']) : null)
            : null;
        if ($description === '') {
            $description = null;
        }

        try {
            $entity = $this->entities->create(
                $chronicle,
                $type,
                $validated['canonical_name'],
                $description,
                typed: $this->typedPayload($type, $validated['subtype'] ?? null),
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        } catch (MixedChronicleException $e) {
            abort(409, $e->getMessage());
        } catch (UniqueConstraintViolationException) {
            abort(422, 'This name is already used in the chronicle.');
        }

        return response()->json(['entity' => $this->serialize($entity)], 201);
    }

    public function update(UpdateWorldEntityRequest $request, WorldEntity $worldEntity): JsonResponse
    {
        $this->assertDirectoryEntity($request, $worldEntity);

        if ($worldEntity->status !== WorldEntityStatus::Active) {
            abort(422, 'Archived entities cannot be edited.');
        }

        $validated = $request->validated();
        $description = array_key_exists('short_description', $validated)
            ? (is_string($validated['short_description']) ? trim($validated['short_description']) : null)
            : null;
        if ($description === '') {
            $description = null;
        }
        $subtype = isset($validated['subtype']) && is_string($validated['subtype']) && trim($validated['subtype']) !== ''
            ? trim($validated['subtype'])
            : null;

        try {
            $entity = $this->entities->update(
                $worldEntity,
                $validated['canonical_name'],
                $description,
                $subtype,
            );
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        } catch (UniqueConstraintViolationException) {
            abort(422, 'This name is already used in the chronicle.');
        }

        return response()->json(['entity' => $this->serialize($entity)]);
    }

    public function archive(Request $request, WorldEntity $worldEntity): JsonResponse
    {
        $this->assertDirectoryEntity($request, $worldEntity);

        return response()->json([
            'entity' => $this->serialize($this->entities->archive($worldEntity)),
        ]);
    }

    public function restore(Request $request, WorldEntity $worldEntity): JsonResponse
    {
        $this->assertDirectoryEntity($request, $worldEntity);

        return response()->json([
            'entity' => $this->serialize($this->entities->restore($worldEntity)),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(WorldEntity $entity): array
    {
        $entity->loadMissing(['faction', 'location', 'item', 'concept']);

        $subtype = match ($entity->entity_type) {
            WorldEntityType::Faction => $entity->faction?->faction_type?->value,
            WorldEntityType::Location => $entity->location?->location_type?->value,
            WorldEntityType::Item => $entity->item?->item_type?->value,
            WorldEntityType::Concept => $entity->concept?->concept_type?->value,
            default => null,
        };

        return [
            'id' => (int) $entity->id,
            'entity_type' => $entity->entity_type->value,
            'canonical_name' => $entity->canonical_name,
            'short_description' => $entity->short_description,
            'subtype' => $subtype,
            'status' => $entity->status->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function typedPayload(WorldEntityType $type, mixed $subtype): array
    {
        if (! is_string($subtype) || trim($subtype) === '') {
            return [];
        }

        $value = trim($subtype);

        return match ($type) {
            WorldEntityType::Faction => ['faction_type' => $value],
            WorldEntityType::Location => ['location_type' => $value],
            WorldEntityType::Item => ['item_type' => $value],
            WorldEntityType::Concept => ['concept_type' => $value],
            default => [],
        };
    }

    private function assertDirectoryEntity(Request $request, WorldEntity $entity): void
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );

        if ((int) $entity->chronicle_id !== $chronicleId) {
            abort(404);
        }

        if (! in_array($entity->entity_type, self::DIRECTORY_TYPES, true)) {
            abort(422, 'This endpoint archives factions, locations, items, and concepts.');
        }
    }
}
