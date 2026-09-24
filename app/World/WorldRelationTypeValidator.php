<?php

namespace App\World;

use App\Enums\WorldEntityType;
use App\Models\WorldEntity;
use App\Models\WorldRelationType;

class WorldRelationTypeValidator
{
    public function assertCompatible(WorldRelationType $type, WorldEntity $source, WorldEntity $target): void
    {
        if (! $type->enabled) {
            throw new WorldRelationTypeException("Relation type [{$type->key}] is disabled.");
        }

        if ((int) $source->chronicle_id !== (int) $target->chronicle_id) {
            throw new MixedChronicleException;
        }

        $sourceType = $this->entityTypeValue($source);
        $targetType = $this->entityTypeValue($target);

        if (! in_array($sourceType, $type->allowedSourceTypeValues(), true)) {
            throw new WorldRelationTypeException(
                "Relation type [{$type->key}] does not allow source entity type [{$sourceType}].",
            );
        }

        if (! in_array($targetType, $type->allowedTargetTypeValues(), true)) {
            throw new WorldRelationTypeException(
                "Relation type [{$type->key}] does not allow target entity type [{$targetType}].",
            );
        }
    }

    private function entityTypeValue(WorldEntity $entity): string
    {
        $type = $entity->entity_type;

        return $type instanceof WorldEntityType ? $type->value : (string) $type;
    }
}
