<?php

namespace App\World;

use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Enums\WorldEntityAliasType;
use App\Models\Character;
use App\Models\WorldEntity;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorldRelationService
{
    /**
     * @var list<WorldEntityType>
     */
    private const AFFILIATION_TARGETS = [
        WorldEntityType::Location,
    ];

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

    public function setSect(Character $character, ?WorldEntity $sect): ?WorldRelation
    {
        if ($sect !== null) {
            $this->assertSectTarget($character, $sect);
        }

        return $this->replaceCharacterSlot(
            $character,
            fn (WorldRelation $row): bool => $row->relation === 'member_of'
                && $row->target?->entity_type === WorldEntityType::Faction,
            $sect,
            'member_of',
            ['stance' => 'allied'],
        );
    }

    public function setHaven(Character $character, ?WorldEntity $haven): ?WorldRelation
    {
        if ($haven !== null) {
            $this->assertHavenTarget($character, $haven);
        }

        return $this->replaceCharacterSlot(
            $character,
            fn (WorldRelation $row): bool => $row->relation === 'located_at'
                && $row->target?->entity_type === WorldEntityType::Location,
            $haven,
            'located_at',
            ['stance' => 'neutral'],
        );
    }

    public function activeSect(Character $character): ?WorldRelation
    {
        return $this->activeCharacterRelations($character)
            ->first(fn (WorldRelation $row): bool => $row->relation === 'member_of'
                && $row->target?->entity_type === WorldEntityType::Faction);
    }

    public function activeHaven(Character $character): ?WorldRelation
    {
        return $this->activeCharacterRelations($character)
            ->first(fn (WorldRelation $row): bool => $row->relation === 'located_at'
                && $row->target?->entity_type === WorldEntityType::Location);
    }

    /**
     * @return Collection<int, WorldRelation>
     */
    public function activeCharacterRelations(Character $character): Collection
    {
        return WorldRelation::query()
            ->active()
            ->where('source_type', 'character')
            ->where('source_id', $character->id)
            ->with('target')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  callable(WorldRelation): bool  $matchesSlot
     * @param  array<string, mixed>  $metadata
     */
    private function replaceCharacterSlot(
        Character $character,
        callable $matchesSlot,
        ?WorldEntity $target,
        string $relationKey,
        array $metadata = [],
    ): ?WorldRelation {
        return DB::transaction(function () use ($character, $matchesSlot, $target, $relationKey, $metadata): ?WorldRelation {
            WorldRelation::query()
                ->where('source_type', 'character')
                ->where('source_id', $character->id)
                ->active()
                ->lockForUpdate()
                ->get();

            $current = $this->activeCharacterRelations($character)->filter($matchesSlot);

            if ($target === null) {
                foreach ($current as $row) {
                    $this->end($row);
                }

                return null;
            }

            $keep = $current->first(
                fn (WorldRelation $row): bool => (int) $row->target_id === (int) $target->id,
            );

            foreach ($current as $row) {
                if ($keep !== null && (int) $row->id === (int) $keep->id) {
                    continue;
                }

                $this->end($row);
            }

            if ($keep !== null) {
                return $keep;
            }

            $source = WorldEntity::query()->findOrFail($character->id);
            $type = WorldRelationType::query()->where('key', $relationKey)->firstOrFail();

            return $this->relate($source, $target, $type, metadata: $metadata);
        });
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

    private function assertSectTarget(Character $character, WorldEntity $sect): void
    {
        $this->assertAffiliationTarget($character, $sect);

        if ($sect->entity_type !== WorldEntityType::Faction) {
            throw new InvalidArgumentException('A character sect must be a faction.');
        }

        if ($sect->status !== WorldEntityStatus::Active) {
            throw new InvalidArgumentException('A character sect must be an active faction.');
        }
    }

    private function assertHavenTarget(Character $character, WorldEntity $haven): void
    {
        $this->assertAffiliationTarget($character, $haven);

        if ($haven->entity_type !== WorldEntityType::Location) {
            throw new InvalidArgumentException('A character haven must be a location.');
        }

        if ($haven->status !== WorldEntityStatus::Active) {
            throw new InvalidArgumentException('A character haven must be an active location.');
        }
    }

    private function assertAffiliationTarget(Character $character, WorldEntity $target): void
    {
        if ((int) $character->chronicle_id !== (int) $target->chronicle_id) {
            throw new MixedChronicleException;
        }

        $type = $target->entity_type instanceof WorldEntityType
            ? $target->entity_type
            : WorldEntityType::from((string) $target->entity_type);

        if (! in_array($type, self::AFFILIATION_TARGETS, true)) {
            throw new InvalidArgumentException(
                "Relation target must be a directory entity, not [{$type->value}].",
            );
        }
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
