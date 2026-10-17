<?php

namespace App\World;

use App\Enums\WorldEntityType;
use App\Enums\WorldRelationKind;
use App\Models\WorldEntity;
use App\Models\WorldRelation;
use Illuminate\Support\Collection;

final class WorldRelationService
{
    /**
     * @return Collection<int, WorldRelation>
     */
    public function neighbors(WorldEntity $entity, ?WorldRelationKind $type = null): Collection
    {
        $entityType = $this->entityTypeValue($entity);
        $incomingKindValues = array_map(
            fn (WorldRelationKind $kind): string => $kind->value,
            WorldRelationKind::incomingEdgeKinds(),
        );

        $outgoing = WorldRelation::query()
            ->active()
            ->where('source_type', $entityType)
            ->where('source_id', $entity->id)
            ->when($type !== null, fn ($query) => $query->where('relation', $type->value))
            ->with(['source', 'target'])
            ->get();

        $incoming = WorldRelation::query()
            ->active()
            ->where('target_type', $entityType)
            ->where('target_id', $entity->id)
            ->when($type !== null, fn ($query) => $query->where('relation', $type->value))
            ->whereIn('relation', $incomingKindValues)
            ->with(['source', 'target'])
            ->get();

        return $outgoing->concat($incoming)->unique('id')->values();
    }

    private function entityTypeValue(WorldEntity $entity): string
    {
        $type = $entity->entity_type;

        return $type instanceof WorldEntityType ? $type->value : (string) $type;
    }
}
