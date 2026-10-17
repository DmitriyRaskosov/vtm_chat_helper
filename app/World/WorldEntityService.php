<?php

namespace App\World;

use App\Enums\CharacterType;
use App\Enums\WorldEntityAliasType;
use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Models\CanonClan;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\Location;
use App\Models\WorldEntity;
use App\Models\WorldEntityAlias;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorldEntityService
{
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

            $this->createTypedRecord($entity, $typed);

            return $entity->load(['aliases', 'location', 'character']);
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

            foreach ($entity->aliases as $alias) {
                $suffix = '--archived-'.$entity->id;
                if (! str_ends_with($alias->normalized_alias, $suffix)) {
                    $alias->normalized_alias = $alias->normalized_alias.$suffix;
                    $alias->save();
                }
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

            foreach ($entity->aliases as $alias) {
                $cleaned = preg_replace('/--archived-\d+$/', '', $alias->normalized_alias);

                if ($cleaned === $alias->normalized_alias) {
                    continue;
                }

                $taken = WorldEntityAlias::query()
                    ->where('chronicle_id', $alias->chronicle_id)
                    ->where('normalized_alias', $cleaned)
                    ->where('id', '!=', $alias->id)
                    ->exists();

                if ($taken) {
                    continue;
                }

                $alias->normalized_alias = $cleaned;
                $alias->save();
            }

            return $entity->refresh();
        });
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

        Character::query()->create([
            'id' => $entity->id,
            'chronicle_id' => $entity->chronicle_id,
            'entity_type' => WorldEntityType::Character,
            'character_type' => $characterType,
            'user_id' => $userId,
            'clan_id' => $clanId,
            'sire_character_id' => $sireId,
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
