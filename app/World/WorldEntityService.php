<?php

namespace App\World;

use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\CanonClan;
use App\Models\Scene;
use App\Models\WorldEntity;
use App\Models\WorldEntityAlias;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorldEntityService
{
    private const SECT_SYNC_PROVENANCE_KEY = 'auto_synced_sect_faction';

    /**
     * @var list<WorldEntityType>
     */
    public const DIRECTORY_TYPES = [
        WorldEntityType::Faction,
        WorldEntityType::Coterie,
        WorldEntityType::Circle,
        WorldEntityType::Other,
        WorldEntityType::Location,
        WorldEntityType::Item,
        WorldEntityType::Concept,
    ];

    public function __construct(private WorldRelationService $relations) {}

    /**
     * @param  list<string>  $aliases
     * @param  array<string, mixed>  $typed
     */
    public function create(
        Chronicle $chronicle,
        WorldEntityType $type,
        string $canonicalName,
        ?string $shortDescription = null,
        array $aliases = [],
        ?string $language = null,
        ?string $slug = null,
        array $typed = [],
    ): WorldEntity {
        return DB::transaction(function () use (
            $chronicle,
            $type,
            $canonicalName,
            $shortDescription,
            $aliases,
            $language,
            $slug,
            $typed,
        ): WorldEntity {
            $entity = WorldEntity::query()->create([
                'chronicle_id' => $chronicle->id,
                'entity_type' => $type,
                'canonical_name' => $canonicalName,
                'slug' => $this->uniqueSlug($chronicle->id, $canonicalName, $slug),
                'short_description' => $shortDescription,
                'status' => WorldEntityStatus::Active,
            ]);

            $this->storeAlias(
                $entity,
                $canonicalName,
                WorldEntityAliasType::Canonical,
                $language,
            );

            $this->createTypedRecord($entity, array_merge($this->defaultTypedPayload($type), $typed));

            return $entity->load($this->directoryRelations());
        });
    }

    /**
     * @param  list<string>|null  $aliases  null — не трогать aka; [] — снять все aka
     */
    public function update(
        WorldEntity $entity,
        string $canonicalName,
        ?string $shortDescription = null,
        ?array $aliases = null,
        bool $updateParentFaction = false,
        ?int $parentFactionId = null,
        bool $updateSectFaction = false,
        ?int $sectFactionId = null,
    ): WorldEntity {
        $type = $entity->entity_type instanceof WorldEntityType
            ? $entity->entity_type
            : WorldEntityType::from((string) $entity->entity_type);

        if (! in_array($type, self::DIRECTORY_TYPES, true)) {
            throw new InvalidArgumentException('Only directory entities can be edited.');
        }

        if ($entity->status !== WorldEntityStatus::Active) {
            throw new InvalidArgumentException('Archived entities cannot be edited.');
        }

        return DB::transaction(function () use (
            $entity,
            $canonicalName,
            $shortDescription,
            $aliases,
            $type,
            $updateParentFaction,
            $parentFactionId,
            $updateSectFaction,
            $sectFactionId,
        ): WorldEntity {
            $name = trim($canonicalName);
            if ($name === '') {
                throw new InvalidArgumentException('A name is required.');
            }

            if ($name !== $entity->canonical_name) {
                $this->renameEntity($entity, $name);
            }

            if ($shortDescription !== null) {
                $description = trim($shortDescription);
                $entity->short_description = $description === '' ? null : $description;
                $entity->save();
            }

            if ($aliases !== null) {
                $this->syncAka($entity, $aliases);
            }

            if ($updateParentFaction) {
                $this->updateParentFaction($entity, $parentFactionId);
            }

            if ($updateSectFaction) {
                $this->updateSectFaction($entity, $sectFactionId);
            }

            return $entity->refresh()->load($this->directoryRelations());
        });
    }

    public function archive(WorldEntity $entity): WorldEntity
    {
        return DB::transaction(function () use ($entity): WorldEntity {
            $entity->update([
                'status' => WorldEntityStatus::Archived,
                'archived_at' => $entity->archived_at ?? now(),
            ]);

            if ($entity->entity_type === WorldEntityType::Character) {
                Character::query()->whereKey($entity->id)->update(['is_active' => false]);
            }

            return $entity->refresh();
        });
    }

    public function restore(WorldEntity $entity): WorldEntity
    {
        return DB::transaction(function () use ($entity): WorldEntity {
            $entity->update([
                'status' => WorldEntityStatus::Active,
                'archived_at' => null,
            ]);

            if ($entity->entity_type === WorldEntityType::Character) {
                Character::query()->whereKey($entity->id)->update(['is_active' => true]);
            }

            return $entity->refresh();
        });
    }

    public function findByAlias(Chronicle $chronicle, string $alias): ?WorldEntity
    {
        $normalized = AliasNormalizer::normalize($alias);

        return WorldEntity::query()
            ->where('chronicle_id', $chronicle->id)
            ->whereHas(
                'aliases',
                fn ($query) => $query->where('normalized_alias', $normalized),
            )
            ->first();
    }

    public function addAka(WorldEntity $entity, string $alias, ?string $language = null): WorldEntityAlias
    {
        if ($entity->status !== WorldEntityStatus::Active) {
            throw new InvalidArgumentException('Archived entities cannot receive aliases.');
        }

        $alias = trim($alias);
        if ($alias === '') {
            throw new InvalidArgumentException('A name is required.');
        }

        $normalized = AliasNormalizer::normalize($alias);

        if ($normalized === AliasNormalizer::normalize((string) $entity->canonical_name)) {
            throw new InvalidArgumentException('Alias must differ from the canonical name.');
        }

        $taken = WorldEntityAlias::query()
            ->where('chronicle_id', $entity->chronicle_id)
            ->where('normalized_alias', $normalized)
            ->first();

        if ($taken !== null) {
            if ((int) $taken->entity_id === (int) $entity->id) {
                return $taken;
            }

            throw new InvalidArgumentException('This name is already used in the chronicle.');
        }

        return $this->storeAlias($entity, $alias, WorldEntityAliasType::Aka, $language);
    }

    /**
     * @param  list<string>  $aliases
     */
    public function syncAka(WorldEntity $entity, array $aliases): void
    {
        if ($entity->status !== WorldEntityStatus::Active) {
            throw new InvalidArgumentException('Archived entities cannot be edited.');
        }

        $wanted = [];
        $canonicalNorm = AliasNormalizer::normalize((string) $entity->canonical_name);

        foreach ($aliases as $alias) {
            $alias = trim($alias);
            if ($alias === '') {
                continue;
            }

            $normalized = AliasNormalizer::normalize($alias);
            if ($normalized === $canonicalNorm) {
                continue;
            }

            $wanted[$normalized] = $alias;
        }

        $existing = WorldEntityAlias::query()
            ->where('entity_id', $entity->id)
            ->where('alias_type', WorldEntityAliasType::Aka)
            ->get();

        foreach ($existing as $row) {
            if (! array_key_exists($row->normalized_alias, $wanted)) {
                $row->delete();
            }
        }

        $kept = $existing->keyBy('normalized_alias');
        foreach ($wanted as $normalized => $alias) {
            if ($kept->has($normalized)) {
                continue;
            }

            $this->addAka($entity, $alias);
        }
    }

    public function assertSameChronicle(Chronicle $chronicle, WorldEntity ...$entities): void
    {
        foreach ($entities as $entity) {
            if ((int) $entity->chronicle_id !== (int) $chronicle->id) {
                throw new MixedChronicleException;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function directoryRelations(): array
    {
        return [
            'aliases',
            'location',
            'character',
        ];
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function createTypedRecord(WorldEntity $entity, array $typed): void
    {
        match ($entity->entity_type) {
            WorldEntityType::Location => $this->insertLocation($entity, $typed),
            WorldEntityType::Character => $this->insertCharacter($entity, $typed),
            default => throw new InvalidArgumentException('Unsupported entity type.'),
        };
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function insertLocation(WorldEntity $entity, array $typed): void
    {
        $parentId = isset($typed['parent_location_id']) ? (int) $typed['parent_location_id'] : null;

        if ($parentId === $entity->id) {
            throw new InvalidArgumentException('A location cannot be its own parent.');
        }

        if ($parentId !== null) {
            $parent = Location::query()->findOrFail($parentId);
            $this->assertSameChronicle(
                $entity->chronicle,
                WorldEntity::query()->findOrFail($parent->id),
            );
        }

        $details = $typed['details'] ?? null;

        Location::query()->create([
            'id' => $entity->id,
            'chronicle_id' => $entity->chronicle_id,
            'entity_type' => WorldEntityType::Location,
            'parent_location_id' => $parentId,
            'details' => is_array($details) ? $details : null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function insertCharacter(WorldEntity $entity, array $typed): void
    {
        $characterType = $typed['character_type'] ?? CharacterType::Npc;
        $characterType = $characterType instanceof CharacterType
            ? $characterType
            : CharacterType::from((string) $characterType);
        $userId = isset($typed['user_id']) ? (int) $typed['user_id'] : null;

        if ($characterType === CharacterType::Player && $userId === null) {
            throw new InvalidArgumentException('A player character must belong to a user.');
        }

        if ($characterType === CharacterType::Npc && $userId !== null) {
            throw new InvalidArgumentException('An NPC cannot belong to a user.');
        }

        $clanId = isset($typed['clan_id']) ? (int) $typed['clan_id'] : null;

        if ($clanId !== null && ! CanonClan::query()->whereKey($clanId)->exists()) {
            throw new InvalidArgumentException('The selected clan does not exist.');
        }

        $sireId = isset($typed['sire_character_id']) ? (int) $typed['sire_character_id'] : null;

        if ($sireId === $entity->id) {
            throw new InvalidArgumentException('A character cannot be their own sire.');
        }

        if ($sireId !== null) {
            $sire = Character::query()->findOrFail($sireId);
            $this->assertSameChronicle(
                $entity->chronicle,
                WorldEntity::query()->findOrFail($sire->id),
            );
        }

        $domitorId = isset($typed['domitor_character_id']) ? (int) $typed['domitor_character_id'] : null;

        Character::query()->create([
            'id' => $entity->id,
            'chronicle_id' => $entity->chronicle_id,
            'entity_type' => WorldEntityType::Character,
            'character_type' => $characterType,
            'user_id' => $userId,
            'clan_id' => $clanId,
            'sire_character_id' => $sireId,
            'domitor_character_id' => $domitorId,
            'generation' => $typed['generation'] ?? null,
            'apparent_age' => $typed['apparent_age'] ?? null,
            'actual_age' => $typed['actual_age'] ?? null,
            'nature' => $typed['nature'] ?? null,
            'demeanor' => $typed['demeanor'] ?? null,
            'concept' => $typed['concept'] ?? null,
            'is_active' => array_key_exists('is_active', $typed) ? (bool) $typed['is_active'] : true,
        ]);
    }

    private function uniqueSlug(int $chronicleId, string $canonicalName, ?string $slug): string
    {
        $base = AliasNormalizer::slug($slug !== null && $slug !== '' ? $slug : $canonicalName);
        $candidate = $base;
        $suffix = 2;

        while (
            WorldEntity::query()
                ->where('chronicle_id', $chronicleId)
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }

    private function renameEntity(WorldEntity $entity, string $name): void
    {
        $normalized = AliasNormalizer::normalize($name);

        $taken = WorldEntityAlias::query()
            ->where('chronicle_id', $entity->chronicle_id)
            ->where('normalized_alias', $normalized)
            ->where('entity_id', '!=', $entity->id)
            ->exists();

        if ($taken) {
            throw new InvalidArgumentException('This name is already used in the chronicle.');
        }

        $entity->update(['canonical_name' => $name]);

        $canonical = $entity->canonicalAlias()->first();
        if ($canonical !== null) {
            $canonical->update([
                'alias' => $name,
                'normalized_alias' => $normalized,
            ]);
        }
    }

    private function updateSectFaction(WorldEntity $entity, ?int $sectFactionId): void
    {
        $type = $entity->entity_type instanceof WorldEntityType
            ? $entity->entity_type
            : WorldEntityType::from((string) $entity->entity_type);

        if (! in_array($type, [WorldEntityType::Coterie, WorldEntityType::Circle], true)) {
            throw new InvalidArgumentException('Only coteries and circles can have a sect faction.');
        }

        if ($sectFactionId !== null) {
            $sect = WorldEntity::query()->findOrFail($sectFactionId);
            $this->assertSectFaction($entity->chronicle, $sect);
        }

        match ($type) {
            WorldEntityType::Coterie => $entity->coterie()->update(['sect_faction_id' => $sectFactionId]),
            WorldEntityType::Circle => $entity->circle()->update(['sect_faction_id' => $sectFactionId]),
            default => null,
        };

        $this->syncSectFactionMemberOf($entity->refresh(), $sectFactionId);
    }

    private function syncSectFactionMemberOf(WorldEntity $entity, ?int $sectFactionId): void
    {
        $type = $entity->entity_type instanceof WorldEntityType
            ? $entity->entity_type
            : WorldEntityType::from((string) $entity->entity_type);

        $memberOf = WorldRelationType::query()->where('key', 'member_of')->firstOrFail();

        $autoSynced = WorldRelation::query()
            ->where('source_type', $this->entityTypeValue($entity))
            ->where('source_id', $entity->id)
            ->where('relation', $memberOf->key)
            ->active()
            ->get()
            ->filter(fn (WorldRelation $relation): bool => ($relation->provenance[self::SECT_SYNC_PROVENANCE_KEY] ?? false) === true);

        foreach ($autoSynced as $relation) {
            if ($sectFactionId === null || (int) $relation->target_id !== $sectFactionId) {
                $this->relations->end($relation);
            }
        }

        if ($sectFactionId === null) {
            return;
        }

        $alreadyActive = WorldRelation::query()
            ->where('source_type', $this->entityTypeValue($entity))
            ->where('source_id', $entity->id)
            ->where('target_id', $sectFactionId)
            ->where('relation', $memberOf->key)
            ->active()
            ->exists();

        if ($alreadyActive) {
            return;
        }

        $sect = WorldEntity::query()->findOrFail($sectFactionId);
        $this->relations->relate(
            $entity,
            $sect,
            $memberOf,
            provenance: [self::SECT_SYNC_PROVENANCE_KEY => true],
        );
    }

    private function entityTypeValue(WorldEntity $entity): string
    {
        $type = $entity->entity_type;

        return $type instanceof WorldEntityType ? $type->value : (string) $type;
    }

    private function storeAlias(
        WorldEntity $entity,
        string $alias,
        WorldEntityAliasType $type,
        ?string $language,
    ): WorldEntityAlias {
        return WorldEntityAlias::query()->create([
            'entity_id' => $entity->id,
            'chronicle_id' => $entity->chronicle_id,
            'alias' => $alias,
            'normalized_alias' => AliasNormalizer::normalize($alias),
            'alias_type' => $type,
            'language' => $language,
        ]);
    }
}
