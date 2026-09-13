<?php

namespace App\World;

use App\Models\WorldEntity;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class WorldRelationService
{
    public function __construct(private WorldRelationTypeValidator $validator) {}

    /**
     * @param  array<string, mixed>|null  $provenance
     */
    public function relate(
        WorldEntity $source,
        WorldEntity $target,
        WorldRelationType $type,
        ?float $weight = null,
        ?string $note = null,
        ?array $provenance = null,
        mixed $startedAt = null,
    ): WorldRelation {
        $this->validator->assertCompatible($type, $source, $target);

        if ((int) $source->id === (int) $target->id) {
            throw new WorldRelationException('A world relation cannot be a self-loop.');
        }

        $note = $note !== null ? trim($note) : null;
        if ($note === '') {
            $note = null;
        }

        return DB::transaction(function () use ($source, $target, $type, $weight, $note, $provenance, $startedAt): WorldRelation {
            $duplicate = WorldRelation::query()
                ->where('chronicle_id', $source->chronicle_id)
                ->where('relation_type_id', $type->id)
                ->whereNull('ended_at')
                ->where(function ($query) use ($source, $target, $type): void {
                    $query->where(function ($inner) use ($source, $target): void {
                        $inner->where('source_entity_id', $source->id)
                            ->where('target_entity_id', $target->id);
                    });

                    if ($type->symmetric) {
                        $query->orWhere(function ($inner) use ($source, $target): void {
                            $inner->where('source_entity_id', $target->id)
                                ->where('target_entity_id', $source->id);
                        });
                    }
                })
                ->lockForUpdate()
                ->exists();

            if ($duplicate) {
                throw new WorldRelationException(
                    "An active [{$type->key}] relation already exists between these entities.",
                );
            }

            return WorldRelation::query()->create([
                'chronicle_id' => $source->chronicle_id,
                'source_entity_id' => $source->id,
                'target_entity_id' => $target->id,
                'relation_type_id' => $type->id,
                'weight' => $weight ?? $type->default_weight,
                'note' => $note,
                'started_at' => $startedAt ?? now(),
                'ended_at' => null,
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
        WorldRelationType $type,
        array $exclusiveKeys,
        ?string $note = null,
    ): WorldRelation {
        return DB::transaction(function () use ($source, $target, $type, $exclusiveKeys, $note): WorldRelation {
            $typeIds = WorldRelationType::query()->whereIn('key', $exclusiveKeys)->pluck('id');
            $existing = WorldRelation::query()
                ->active()
                ->where('chronicle_id', $source->chronicle_id)
                ->whereIn('relation_type_id', $typeIds)
                ->where(function ($query) use ($source, $target): void {
                    $query->where(function ($inner) use ($source, $target): void {
                        $inner->where('source_entity_id', $source->id)
                            ->where('target_entity_id', $target->id);
                    })->orWhere(function ($inner) use ($source, $target): void {
                        $inner->where('source_entity_id', $target->id)
                            ->where('target_entity_id', $source->id);
                    });
                })
                ->lockForUpdate()
                ->get();

            $keep = $existing->first(
                fn (WorldRelation $edge): bool => (int) $edge->relation_type_id === (int) $type->id,
            );

            foreach ($existing as $edge) {
                if ($keep !== null && (int) $edge->id === (int) $keep->id) {
                    continue;
                }

                $this->end($edge);
            }

            if ($keep !== null) {
                if ($note !== null) {
                    $trimmed = trim($note);
                    $keep->note = $trimmed === '' ? null : $trimmed;
                    $keep->save();
                }

                return $keep->refresh();
            }

            return $this->relate($source, $target, $type, note: $note);
        });
    }

    public function end(WorldRelation $relation, mixed $endedAt = null): WorldRelation
    {
        if ($relation->ended_at !== null) {
            return $relation;
        }

        $relation->ended_at = $endedAt ?? now();
        $relation->save();

        return $relation->refresh();
    }

    /**
     * Directed outgoing edges, plus incoming edges whose type is symmetric.
     *
     * @return Collection<int, WorldRelation>
     */
    public function neighbors(WorldEntity $entity, ?WorldRelationType $type = null): Collection
    {
        $outgoing = WorldRelation::query()
            ->active()
            ->where('source_entity_id', $entity->id)
            ->when($type !== null, fn ($query) => $query->where('relation_type_id', $type->id))
            ->with(['source', 'target', 'type'])
            ->get();

        $incoming = WorldRelation::query()
            ->active()
            ->where('target_entity_id', $entity->id)
            ->whereHas('type', fn ($query) => $query->where('symmetric', true))
            ->when($type !== null, fn ($query) => $query->where('relation_type_id', $type->id))
            ->with(['source', 'target', 'type'])
            ->get();

        return $outgoing->concat($incoming)->unique('id')->values();
    }
}
