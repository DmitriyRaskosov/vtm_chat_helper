<?php

namespace App\World;

use App\Enums\CharacterType;
use App\Enums\FactionStatus;
use App\Enums\ItemStatus;
use App\Enums\LoreAccessLevel;
use App\Enums\WorldEntityAliasType;
use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Enums\WorldEventStatus;
use App\Enums\WorldEventType;
use App\Enums\WorldEventVisibility;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\Circle;
use App\Models\Clan;
use App\Models\Concept;
use App\Models\Coterie;
use App\Models\Faction;
use App\Models\Item;
use App\Models\Location;
use App\Models\Other;
use App\Models\Scene;
use App\Models\WorldEntity;
use App\Models\WorldEntityAlias;
use App\Models\WorldEvent;
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
        WorldEntityType::Clan,
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

            $canonicalNormalized = AliasNormalizer::normalize($canonicalName);

            foreach ($aliases as $alias) {
                if (AliasNormalizer::normalize($alias) === $canonicalNormalized) {
                    continue;
                }

                $this->storeAlias($entity, $alias, WorldEntityAliasType::Aka, $language);
            }

            $this->createTypedRecord($entity, array_merge($this->defaultTypedPayload($type), $typed));

            if (isset($typed['sect_faction_id']) || array_key_exists('sect_faction_id', $typed)) {
                $sectId = $typed['sect_faction_id'] !== null ? (int) $typed['sect_faction_id'] : null;
                $this->syncSectFactionMemberOf($entity->refresh(), $sectId);
            }

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

    public function assertClan(Chronicle $chronicle, WorldEntity $clan): void
    {
        $this->assertSameChronicle($chronicle, $clan);

        $type = $clan->entity_type instanceof WorldEntityType
            ? $clan->entity_type
            : WorldEntityType::from((string) $clan->entity_type);

        if ($type !== WorldEntityType::Clan) {
            throw new InvalidArgumentException('A character clan must be a clan in the same chronicle.');
        }

        if ($clan->status !== WorldEntityStatus::Active) {
            throw new InvalidArgumentException('A character clan must be an active clan.');
        }
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

    /**
     * @return array<string, mixed>
     */
    public function defaultTypedPayload(WorldEntityType $type): array
    {
        return match ($type) {
            WorldEntityType::Faction => ['status' => FactionStatus::Active],
            WorldEntityType::Clan, WorldEntityType::Coterie, WorldEntityType::Circle => [
                'status' => FactionStatus::Active,
                'sect_faction_id' => null,
            ],
            WorldEntityType::Other => ['status' => FactionStatus::Active],
            WorldEntityType::Item => ['status' => ItemStatus::Intact],
            default => [],
        };
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
            'faction',
            'clan',
            'coterie',
            'circle',
            'other',
            'item',
            'concept',
            'character',
            'event',
        ];
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function createTypedRecord(WorldEntity $entity, array $typed): void
    {
        match ($entity->entity_type) {
            WorldEntityType::Location => $this->insertLocation($entity, $typed),
            WorldEntityType::Faction => $this->insertFaction($entity, $typed),
            WorldEntityType::Clan => $this->insertClan($entity, $typed),
            WorldEntityType::Coterie => $this->insertCoterie($entity, $typed),
            WorldEntityType::Circle => $this->insertCircle($entity, $typed),
            WorldEntityType::Other => $this->insertOther($entity, $typed),
            WorldEntityType::Item => $this->insertItem($entity, $typed),
            WorldEntityType::Concept => $this->insertConcept($entity, $typed),
            WorldEntityType::Character => $this->insertCharacter($entity, $typed),
            WorldEntityType::Event => $this->insertWorldEvent($entity, $typed),
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
    private function insertFaction(WorldEntity $entity, array $typed): void
    {
        $parentId = isset($typed['parent_faction_id']) ? (int) $typed['parent_faction_id'] : null;

        if ($parentId === $entity->id) {
            throw new InvalidArgumentException('A faction cannot be its own parent.');
        }

        if ($parentId !== null) {
            $parent = Faction::query()->findOrFail($parentId);
            $this->assertSameChronicle(
                $entity->chronicle,
                WorldEntity::query()->findOrFail($parent->id),
            );
        }

        $status = $typed['status'] ?? FactionStatus::Active;

        Faction::query()->create([
            'id' => $entity->id,
            'chronicle_id' => $entity->chronicle_id,
            'entity_type' => WorldEntityType::Faction,
            'parent_faction_id' => $parentId,
            'status' => $status instanceof FactionStatus
                ? $status
                : FactionStatus::from((string) $status),
        ]);
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function insertSectAffiliatedGroup(WorldEntity $entity, array $typed, WorldEntityType $type): void
    {
        $sectId = isset($typed['sect_faction_id']) ? (int) $typed['sect_faction_id'] : null;

        if ($sectId !== null) {
            $sect = WorldEntity::query()->findOrFail($sectId);
            $this->assertSectFaction($entity->chronicle, $sect);
        }

        $status = $typed['status'] ?? FactionStatus::Active;
        $payload = [
            'id' => $entity->id,
            'chronicle_id' => $entity->chronicle_id,
            'entity_type' => $type,
            'sect_faction_id' => $sectId,
            'status' => $status instanceof FactionStatus
                ? $status
                : FactionStatus::from((string) $status),
        ];

        match ($type) {
            WorldEntityType::Clan => Clan::query()->create($payload),
            WorldEntityType::Coterie => Coterie::query()->create($payload),
            WorldEntityType::Circle => Circle::query()->create($payload),
            default => throw new InvalidArgumentException('Unsupported sect-affiliated type.'),
        };
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function insertClan(WorldEntity $entity, array $typed): void
    {
        $this->insertSectAffiliatedGroup($entity, $typed, WorldEntityType::Clan);
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function insertCoterie(WorldEntity $entity, array $typed): void
    {
        $this->insertSectAffiliatedGroup($entity, $typed, WorldEntityType::Coterie);
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function insertCircle(WorldEntity $entity, array $typed): void
    {
        $this->insertSectAffiliatedGroup($entity, $typed, WorldEntityType::Circle);
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function insertOther(WorldEntity $entity, array $typed): void
    {
        $status = $typed['status'] ?? FactionStatus::Active;

        Other::query()->create([
            'id' => $entity->id,
            'chronicle_id' => $entity->chronicle_id,
            'entity_type' => WorldEntityType::Other,
            'status' => $status instanceof FactionStatus
                ? $status
                : FactionStatus::from((string) $status),
        ]);
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function insertItem(WorldEntity $entity, array $typed): void
    {
        $ownerId = isset($typed['owner_entity_id']) ? (int) $typed['owner_entity_id'] : null;

        if ($ownerId !== null) {
            $owner = WorldEntity::query()->findOrFail($ownerId);
            $this->assertSameChronicle($entity->chronicle, $owner);
        }

        $status = $typed['status'] ?? ItemStatus::Intact;

        Item::query()->create([
            'id' => $entity->id,
            'chronicle_id' => $entity->chronicle_id,
            'entity_type' => WorldEntityType::Item,
            'owner_entity_id' => $ownerId,
            'status' => $status instanceof ItemStatus
                ? $status
                : ItemStatus::from((string) $status),
        ]);
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function insertConcept(WorldEntity $entity, array $typed): void
    {
        Concept::query()->create([
            'id' => $entity->id,
            'chronicle_id' => $entity->chronicle_id,
            'entity_type' => WorldEntityType::Concept,
            'definition' => $typed['definition'] ?? $entity->short_description,
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

        if ($characterType === CharacterType::Ghoul && $userId !== null) {
            throw new InvalidArgumentException('A ghoul cannot belong to a user.');
        }

        $clanId = isset($typed['clan_entity_id']) ? (int) $typed['clan_entity_id'] : null;

        if ($clanId !== null) {
            $clan = WorldEntity::query()->findOrFail($clanId);
            $this->assertClan($entity->chronicle, $clan);
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

        if ($characterType === CharacterType::Ghoul) {
            if ($domitorId === null) {
                throw new InvalidArgumentException('A ghoul must have a domitor.');
            }

            if ($domitorId === $entity->id) {
                throw new InvalidArgumentException('A character cannot be their own domitor.');
            }

            $domitor = Character::query()->findOrFail($domitorId);
            $this->assertSameChronicle(
                $entity->chronicle,
                WorldEntity::query()->findOrFail($domitor->id),
            );

            if ($domitor->character_type === CharacterType::Ghoul) {
                throw new InvalidArgumentException('A ghoul domitor must be a player or NPC character.');
            }
        } elseif ($domitorId !== null) {
            throw new InvalidArgumentException('Only a ghoul can have a domitor.');
        }

        Character::query()->create([
            'id' => $entity->id,
            'chronicle_id' => $entity->chronicle_id,
            'entity_type' => WorldEntityType::Character,
            'character_type' => $characterType,
            'user_id' => $userId,
            'clan_entity_id' => $clanId,
            'sire_character_id' => $sireId,
            'domitor_character_id' => $domitorId,
            'generation' => $typed['generation'] ?? null,
            'apparent_age' => $typed['apparent_age'] ?? null,
            'actual_age' => $typed['actual_age'] ?? null,
            'nature' => $typed['nature'] ?? null,
            'demeanor' => $typed['demeanor'] ?? null,
            'concept' => $typed['concept'] ?? null,
            'lore_clearance_levels' => $typed['lore_clearance_levels'] ?? [LoreAccessLevel::L0->rank()],
            'is_active' => array_key_exists('is_active', $typed) ? (bool) $typed['is_active'] : true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function insertWorldEvent(WorldEntity $entity, array $typed): void
    {
        $sceneId = isset($typed['scene_id']) ? (int) $typed['scene_id'] : null;

        if ($sceneId !== null) {
            $scene = Scene::query()->with('gameSession')->findOrFail($sceneId);

            if ((int) $scene->gameSession->chronicle_id !== (int) $entity->chronicle_id) {
                throw new MixedChronicleException;
            }
        }

        $eventType = $typed['event_type'] ?? WorldEventType::Other;
        $status = $typed['status'] ?? WorldEventStatus::Proposed;
        $visibility = $typed['visibility'] ?? WorldEventVisibility::Public;
        $importance = $typed['importance'] ?? 1;

        if (! is_int($importance) || $importance < 0 || $importance > 5) {
            throw new InvalidArgumentException('Event importance must be an integer between 0 and 5.');
        }

        WorldEvent::query()->create([
            'id' => $entity->id,
            'chronicle_id' => $entity->chronicle_id,
            'entity_type' => WorldEntityType::Event,
            'scene_id' => $sceneId,
            'title' => $typed['title'] ?? $entity->canonical_name,
            'description' => $typed['description'] ?? $entity->short_description,
            'event_type' => $eventType instanceof WorldEventType
                ? $eventType
                : WorldEventType::from((string) $eventType),
            'status' => $status instanceof WorldEventStatus
                ? $status
                : WorldEventStatus::from((string) $status),
            'importance' => $importance,
            'visibility' => $visibility instanceof WorldEventVisibility
                ? $visibility
                : WorldEventVisibility::from((string) $visibility),
            'approved_at' => $typed['approved_at'] ?? null,
            'approved_by' => isset($typed['approved_by']) ? (int) $typed['approved_by'] : null,
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

    private function updateParentFaction(WorldEntity $entity, ?int $parentFactionId): void
    {
        $type = $entity->entity_type instanceof WorldEntityType
            ? $entity->entity_type
            : WorldEntityType::from((string) $entity->entity_type);

        if ($type !== WorldEntityType::Faction) {
            throw new InvalidArgumentException('Only factions can have a parent faction.');
        }

        if ($parentFactionId === (int) $entity->id) {
            throw new InvalidArgumentException('A faction cannot be its own parent.');
        }

        if ($parentFactionId !== null) {
            $parent = Faction::query()->findOrFail($parentFactionId);
            $this->assertSameChronicle(
                $entity->chronicle,
                WorldEntity::query()->findOrFail($parent->id),
            );
        }

        $entity->faction()->update([
            'parent_faction_id' => $parentFactionId,
        ]);
    }

    private function updateSectFaction(WorldEntity $entity, ?int $sectFactionId): void
    {
        $type = $entity->entity_type instanceof WorldEntityType
            ? $entity->entity_type
            : WorldEntityType::from((string) $entity->entity_type);

        if (! in_array($type, [WorldEntityType::Clan, WorldEntityType::Coterie, WorldEntityType::Circle], true)) {
            throw new InvalidArgumentException('Only clans, coteries, and circles can have a sect faction.');
        }

        if ($sectFactionId !== null) {
            $sect = WorldEntity::query()->findOrFail($sectFactionId);
            $this->assertSectFaction($entity->chronicle, $sect);
        }

        match ($type) {
            WorldEntityType::Clan => $entity->clan()->update(['sect_faction_id' => $sectFactionId]),
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

        if (! in_array($type, [WorldEntityType::Clan, WorldEntityType::Coterie, WorldEntityType::Circle], true)) {
            return;
        }

        $memberOf = WorldRelationType::query()->where('key', 'member_of')->firstOrFail();

        $autoSynced = WorldRelation::query()
            ->where('source_entity_id', $entity->id)
            ->where('relation_type_id', $memberOf->id)
            ->active()
            ->get()
            ->filter(fn (WorldRelation $relation): bool => ($relation->provenance[self::SECT_SYNC_PROVENANCE_KEY] ?? false) === true);

        foreach ($autoSynced as $relation) {
            if ($sectFactionId === null || (int) $relation->target_entity_id !== $sectFactionId) {
                $this->relations->end($relation);
            }
        }

        if ($sectFactionId === null) {
            return;
        }

        $alreadyActive = WorldRelation::query()
            ->where('source_entity_id', $entity->id)
            ->where('target_entity_id', $sectFactionId)
            ->where('relation_type_id', $memberOf->id)
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

    private function assertSectFaction(Chronicle $chronicle, WorldEntity $faction): void
    {
        $this->assertSameChronicle($chronicle, $faction);

        if ($faction->entity_type !== WorldEntityType::Faction) {
            throw new InvalidArgumentException('Sect must be a faction.');
        }

        if ($faction->status !== WorldEntityStatus::Active) {
            throw new InvalidArgumentException('Sect must be an active faction.');
        }
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
