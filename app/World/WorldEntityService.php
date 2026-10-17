<?php

namespace App\World;

use App\Enums\CharacterType;
use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Models\CanonClan;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\Location;
use App\Models\WorldEntity;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorldEntityService
{
    /**
     * @param  array<string, mixed>  $typed
     */
    public function create(
        Chronicle $chronicle,
        WorldEntityType $type,
        string $canonicalName,
        ?string $shortDescription = null,
        ?string $slug = null,
        array $typed = [],
    ): WorldEntity {
        $canonicalName = $this->prepareCanonicalName($canonicalName);

        try {
            return DB::transaction(function () use (
                $chronicle,
                $type,
                $canonicalName,
                $shortDescription,
                $slug,
                $typed,
            ): WorldEntity {
                $this->assertCanonicalNameAvailable($chronicle, $canonicalName);

                $entity = WorldEntity::query()->create([
                    'chronicle_id' => $chronicle->id,
                    'entity_type' => $type,
                    'canonical_name' => $canonicalName,
                    'slug' => $this->uniqueSlug($chronicle->id, $canonicalName, $slug),
                    'short_description' => $shortDescription,
                    'status' => WorldEntityStatus::Active,
                ]);

                $this->createTypedRecord($entity, $typed);

                return $entity->load(['location', 'character']);
            });
        } catch (UniqueConstraintViolationException) {
            throw new InvalidArgumentException('This name is already used in the chronicle.');
        }
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

    public function assertSameChronicle(Chronicle $chronicle, WorldEntity ...$entities): void
    {
        foreach ($entities as $entity) {
            if ((int) $entity->chronicle_id !== (int) $chronicle->id) {
                throw new MixedChronicleException;
            }
        }
    }

    public function prepareCanonicalName(string $value): string
    {
        $value = trim($value);
        $collapsed = preg_replace('/\s+/u', ' ', $value);

        return $collapsed === null ? $value : $collapsed;
    }

    public function assertCanonicalNameAvailable(
        Chronicle $chronicle,
        string $canonicalName,
        ?int $exceptEntityId = null,
    ): void {
        $key = $this->canonicalNameKey($canonicalName);

        $query = WorldEntity::query()
            ->where('chronicle_id', $chronicle->id)
            ->whereRaw('lower(canonical_name) = ?', [$key]);

        if ($exceptEntityId !== null) {
            $query->where('id', '!=', $exceptEntityId);
        }

        if ($query->exists()) {
            throw new InvalidArgumentException('This name is already used in the chronicle.');
        }
    }

    /**
     * @param  array<string, mixed>  $typed
     */
    private function createTypedRecord(WorldEntity $entity, array $typed): void
    {
        if ($entity->entity_type === WorldEntityType::Location) {
            $this->insertLocation($entity, $typed);

            return;
        }

        $this->insertCharacter($entity, $typed);
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

    private function canonicalNameKey(string $canonicalName): string
    {
        return mb_strtolower($this->prepareCanonicalName($canonicalName), 'UTF-8');
    }

    private function uniqueSlug(int $chronicleId, string $canonicalName, ?string $slug): string
    {
        $base = $this->slugFromName($slug !== null && $slug !== '' ? $slug : $canonicalName);
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

    private function slugFromName(string $value): string
    {
        $value = $this->canonicalNameKey($value);
        $value = str_replace(' ', '-', $value);
        $slug = preg_replace('/[^\p{L}\p{N}\-]+/u', '', $value);
        $slug = $slug === null ? '' : trim($slug, '-');

        return $slug === '' ? 'entity' : $slug;
    }
}
