<?php

namespace App\Http\Controllers;

use App\Enums\WorldEntityAliasType;
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
    public function __construct(private WorldEntityService $entities) {}

    public function index(Request $request): JsonResponse
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );
        $types = array_map(fn (WorldEntityType $type): string => $type->value, WorldEntityService::DIRECTORY_TYPES);

        $query = WorldEntity::query()
            ->where('chronicle_id', $chronicleId)
            ->whereIn('entity_type', $types)
            ->with([
                'faction',
                'coterie',
                'circle',
                'other',
                'location',
                'item',
                'concept',
                'aliases',
            ])
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

        $typed = $this->entities->defaultTypedPayload($type);
        if ($type === WorldEntityType::Faction && array_key_exists('parent_faction_id', $validated)) {
            $typed['parent_faction_id'] = $validated['parent_faction_id'];
        }
        if (in_array($type, [WorldEntityType::Coterie, WorldEntityType::Circle], true)
            && array_key_exists('sect_faction_id', $validated)) {
            $typed['sect_faction_id'] = $validated['sect_faction_id'];
        }

        try {
            $entity = $this->entities->create(
                $chronicle,
                $type,
                $validated['canonical_name'],
                $description,
                aliases: $this->akaList($validated['aliases'] ?? []),
                typed: $typed,
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
        $aliases = array_key_exists('aliases', $validated)
            ? $this->akaList($validated['aliases'] ?? [])
            : null;
        $updateParentFaction = array_key_exists('parent_faction_id', $validated);
        $parentFactionId = $updateParentFaction && $validated['parent_faction_id'] !== null
            ? (int) $validated['parent_faction_id']
            : null;
        $updateSectFaction = array_key_exists('sect_faction_id', $validated);
        $sectFactionId = $updateSectFaction && $validated['sect_faction_id'] !== null
            ? (int) $validated['sect_faction_id']
            : null;

        try {
            $entity = $this->entities->update(
                $worldEntity,
                $validated['canonical_name'],
                $description,
                $aliases,
                $updateParentFaction,
                $parentFactionId,
                $updateSectFaction,
                $sectFactionId,
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
        $entity->loadMissing([
            'faction',
            'coterie',
            'circle',
            'other',
            'location',
            'item',
            'concept',
            'aliases',
        ]);

        $aliases = $entity->aliases
            ->filter(fn ($alias): bool => $alias->alias_type === WorldEntityAliasType::Aka)
            ->map(fn ($alias): string => (string) $alias->alias)
            ->values()
            ->all();

        return [
            'id' => (int) $entity->id,
            'entity_type' => $entity->entity_type->value,
            'canonical_name' => $entity->canonical_name,
            'short_description' => $entity->short_description,
            'aliases' => $aliases,
            'parent_faction_id' => $entity->entity_type === WorldEntityType::Faction && $entity->faction?->parent_faction_id !== null
                ? (int) $entity->faction->parent_faction_id
                : null,
            'sect_faction_id' => $this->sectFactionId($entity),
            'status' => $entity->status->value,
        ];
    }

    private function sectFactionId(WorldEntity $entity): ?int
    {
        $id = match ($entity->entity_type) {
            WorldEntityType::Coterie => $entity->coterie?->sect_faction_id,
            WorldEntityType::Circle => $entity->circle?->sect_faction_id,
            default => null,
        };

        return $id !== null ? (int) $id : null;
    }

    /**
     * @param  list<mixed>  $raw
     * @return list<string>
     */
    private function akaList(array $raw): array
    {
        $aliases = [];
        foreach ($raw as $item) {
            if (! is_string($item)) {
                continue;
            }
            $alias = trim($item);
            if ($alias !== '') {
                $aliases[] = $alias;
            }
        }

        return array_values($aliases);
    }

    private function assertDirectoryEntity(Request $request, WorldEntity $entity): void
    {
        $chronicleId = Chronicle::resolveId(
            $request->filled('chronicle_id') ? $request->integer('chronicle_id') : null,
        );

        if ((int) $entity->chronicle_id !== $chronicleId) {
            abort(404);
        }

        if (! in_array($entity->entity_type, WorldEntityService::DIRECTORY_TYPES, true)) {
            abort(422, 'This endpoint archives directory entities only.');
        }
    }
}
