<?php

namespace App\World;

use App\Enums\WorldEntityType;
use App\Models\WorldEntity;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorldRelationService
{
    public function __construct(private WorldRelationTypeValidator $validator) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     * @param  array<string, mixed>|null  $provenance
     */
    public function relate(
        WorldEntity $source,
        WorldEntity $target,
        WorldRelationType|string $type,
        ?float $weight = null,
        ?string $note = null,
        ?array $metadata = null,
        ?int $intensity = null,
        ?array $provenance = null,
        mixed $validFrom = null,
    ): WorldRelation {
        $typeModel = $this->resolveType($type);
        $this->validator->assertCompatible($typeModel, $source, $target);

        if ((int) $source->id === (int) $target->id) {
            throw new WorldRelationException('A world relation cannot be a self-loop.');
        }

        $note = $this->normalizeNote($note);

        return DB::transaction(function () use (
            $source,
            $target,
            $typeModel,
            $weight,
            $note,
            $metadata,
            $intensity,
            $provenance,
            $validFrom,
        ): WorldRelation {
            $duplicate = $this->activeBetween($source, $target, $typeModel->key, $typeModel->symmetric);

            if ($duplicate) {
                throw new WorldRelationException(
                    "An active [{$typeModel->key}] relation already exists between these entities.",
                );
            }

            return WorldRelation::query()->create([
                'chronicle_id' => $source->chronicle_id,
                'source_type' => $this->entityTypeValue($source),
                'source_id' => $source->id,
                'target_type' => $this->entityTypeValue($target),
                'target_id' => $target->id,
                'relation' => $typeModel->key,
                'source_of_truth' => 'chronicle',
                'intensity' => $intensity,
                'metadata' => $metadata,
                'weight' => $weight ?? $typeModel->default_weight,
                'note' => $note,
                'valid_from' => $validFrom ?? now(),
                'valid_to' => null,
                'provenance' => $provenance ?? [],
            ])->refresh();
        });
    }

    /**
     * One active edge among mutually exclusive types for an unordered pair.
     *
     * @param  list<string>  $exclusiveKeys
     */
    public function replaceAmong(
        WorldEntity $source,
        WorldEntity $target,
        WorldRelationType|string $type,
        array $exclusiveKeys,
        ?string $note = null,
    ): WorldRelation {
        $typeModel = $this->resolveType($type);

        return DB::transaction(function () use ($source, $target, $typeModel, $exclusiveKeys, $note): WorldRelation {
            $existing = WorldRelation::query()
                ->active()
                ->where('chronicle_id', $source->chronicle_id)
                ->whereIn('relation', $exclusiveKeys)
                ->where(function ($query) use ($source, $target): void {
                    $query->where(function ($inner) use ($source, $target): void {
                        $inner->where('source_type', $this->entityTypeValue($source))
                            ->where('source_id', $source->id)
                            ->where('target_type', $this->entityTypeValue($target))
                            ->where('target_id', $target->id);
                    })->orWhere(function ($inner) use ($source, $target): void {
                        $inner->where('source_type', $this->entityTypeValue($target))
                            ->where('source_id', $target->id)
                            ->where('target_type', $this->entityTypeValue($source))
                            ->where('target_id', $source->id);
                    });
                })
                ->lockForUpdate()
                ->get();

            $keep = $existing->first(
                fn (WorldRelation $edge): bool => $edge->relation === $typeModel->key,
            );

            foreach ($existing as $edge) {
                if ($keep !== null && (int) $edge->id === (int) $keep->id) {
                    continue;
                }

                $this->end($edge);
            }

            if ($keep !== null) {
                if ($note !== null) {
                    $keep->note = $this->normalizeNote($note);
                    $keep->save();
                }

                return $keep->refresh();
            }

            return $this->relate($source, $target, $typeModel, note: $note);
        });
    }

    public function end(WorldRelation $relation, mixed $endedAt = null): WorldRelation
    {
        if ($relation->valid_to !== null) {
            return $relation;
        }

        $relation->valid_to = $endedAt ?? now();
        $relation->save();

        return $relation->refresh();
    }

    /**
     * @return Collection<int, WorldRelation>
     */
    public function neighbors(WorldEntity $entity, WorldRelationType|string|null $type = null): Collection
    {
        $typeKey = $type === null ? null : $this->resolveType($type)->key;
        $entityType = $this->entityTypeValue($entity);

        $outgoing = WorldRelation::query()
            ->active()
            ->where('source_type', $entityType)
            ->where('source_id', $entity->id)
            ->when($typeKey !== null, fn ($query) => $query->where('relation', $typeKey))
            ->with(['source', 'target'])
            ->get();

        $incoming = WorldRelation::query()
            ->active()
            ->where('target_type', $entityType)
            ->where('target_id', $entity->id)
            ->when($typeKey !== null, fn ($query) => $query->where('relation', $typeKey))
            ->where(function ($query): void {
                $query->whereIn('relation', function ($sub): void {
                    $sub->select('key')
                        ->from('world_relation_types')
                        ->where('symmetric', true);
                })->orWhereIn('relation', function ($sub): void {
                    $sub->select('key')
                        ->from('world_relation_types')
                        ->whereNotNull('inverse_key');
                });
            })
            ->with(['source', 'target'])
            ->get();

        return $outgoing->concat($incoming)->unique('id')->values();
    }

    private function activeBetween(
        WorldEntity $source,
        WorldEntity $target,
        string $relationKey,
        bool $symmetric,
    ): bool {
        $query = WorldRelation::query()
            ->active()
            ->where('chronicle_id', $source->chronicle_id)
            ->where('relation', $relationKey)
            ->where(function ($inner) use ($source, $target, $symmetric): void {
                $inner->where(function ($pair) use ($source, $target): void {
                    $pair->where('source_type', $this->entityTypeValue($source))
                        ->where('source_id', $source->id)
                        ->where('target_type', $this->entityTypeValue($target))
                        ->where('target_id', $target->id);
                });

                if ($symmetric) {
                    $inner->orWhere(function ($pair) use ($source, $target): void {
                        $pair->where('source_type', $this->entityTypeValue($target))
                            ->where('source_id', $target->id)
                            ->where('target_type', $this->entityTypeValue($source))
                            ->where('target_id', $source->id);
                    });
                }
            });

        return $query->lockForUpdate()->exists();
    }

    private function resolveType(WorldRelationType|string $type): WorldRelationType
    {
        if ($type instanceof WorldRelationType) {
            return $type;
        }

        return WorldRelationType::query()->where('key', $type)->firstOrFail();
    }

    private function entityTypeValue(WorldEntity $entity): string
    {
        $type = $entity->entity_type;

        return $type instanceof WorldEntityType ? $type->value : (string) $type;
    }

    private function normalizeNote(?string $note): ?string
    {
        if ($note === null) {
            return null;
        }

        $text = trim($note);

        return $text === '' ? null : $text;
    }
}
