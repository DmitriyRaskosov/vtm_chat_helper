<?php

namespace App\Character;

use App\Enums\CharacterAffiliationStance;
use App\Enums\CharacterAffiliationType;
use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\CharacterAffiliation;
use App\Models\CharacterAffiliationChange;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use App\Models\WorldRelation;
use App\Models\WorldRelationType;
use App\World\MixedChronicleException;
use App\World\WorldRelationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CharacterAffiliationService
{
    private const METRIC_FIELDS = ['trust', 'loyalty', 'fear', 'obligation'];

    private const TEXT_FIELDS = ['role', 'rank', 'note'];

    /**
     * @var list<WorldEntityType>
     */
    private const TARGET_TYPES = [
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
     * @param  array<string, mixed>|null  $provenance
     */
    public function attach(
        Character $character,
        WorldEntity $target,
        CharacterAffiliationType $affiliationType,
        CharacterAffiliationStance $stance,
        ?WorldRelationType $relationType = null,
        int $trust = 0,
        int $loyalty = 0,
        int $fear = 0,
        int $obligation = 0,
        ?string $role = null,
        ?string $rank = null,
        ?string $note = null,
        ?array $provenance = null,
    ): CharacterAffiliation {
        $this->assertAllowedTarget($character, $target);

        foreach (['trust' => $trust, 'loyalty' => $loyalty, 'fear' => $fear, 'obligation' => $obligation] as $field => $value) {
            $this->assertMetric($field, $value);
        }

        $relationType ??= WorldRelationType::query()->where('key', 'affiliated_with')->firstOrFail();

        return DB::transaction(function () use (
            $character,
            $target,
            $affiliationType,
            $stance,
            $relationType,
            $trust,
            $loyalty,
            $fear,
            $obligation,
            $role,
            $rank,
            $note,
            $provenance,
        ): CharacterAffiliation {
            $source = WorldEntity::query()->findOrFail($character->id);
            $edge = $this->relations->relate(
                $source,
                $target,
                $relationType,
                provenance: $provenance,
            );

            return CharacterAffiliation::query()->create([
                'id' => $edge->id,
                'chronicle_id' => $character->chronicle_id,
                'character_id' => $character->id,
                'target_entity_id' => $target->id,
                'affiliation_type' => $affiliationType,
                'stance' => $stance,
                'trust' => $trust,
                'loyalty' => $loyalty,
                'fear' => $fear,
                'obligation' => $obligation,
                'role' => $this->normalizeText($role),
                'rank' => $this->normalizeText($rank),
                'note' => $this->normalizeText($note),
                'revision' => 1,
            ])->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function apply(
        CharacterAffiliation $affiliation,
        array $fields,
        int $expectedRevision,
        ?string $reason = null,
        ?Scene $scene = null,
        ?Message $message = null,
        ?User $changedBy = null,
    ): CharacterAffiliation {
        $allowed = [
            'affiliation_type',
            'stance',
            'trust',
            'loyalty',
            'fear',
            'obligation',
            'role',
            'rank',
            'note',
        ];

        foreach (array_keys($fields) as $field) {
            if (! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException("Unknown affiliation field [{$field}].");
            }
        }

        return DB::transaction(function () use (
            $affiliation,
            $fields,
            $expectedRevision,
            $reason,
            $scene,
            $message,
            $changedBy,
        ): CharacterAffiliation {
            $current = CharacterAffiliation::query()
                ->whereKey($affiliation->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((int) $current->revision !== $expectedRevision) {
                throw new CharacterAffiliationRevisionException($expectedRevision, (int) $current->revision);
            }

            $nextRevision = (int) $current->revision + 1;
            $dirty = false;

            foreach ($fields as $field => $raw) {
                $newValue = $this->normalizeField($field, $raw);
                $oldValue = $this->export($current->{$field});

                if ($oldValue === $newValue) {
                    continue;
                }

                CharacterAffiliationChange::query()->create([
                    'affiliation_id' => $current->id,
                    'character_id' => $current->character_id,
                    'field' => $field,
                    'old_value' => ['v' => $oldValue],
                    'new_value' => ['v' => $newValue],
                    'reason' => $reason,
                    'scene_id' => $scene?->id,
                    'message_id' => $message?->id,
                    'changed_by' => $changedBy?->id,
                    'revision' => $nextRevision,
                ]);

                $current->{$field} = $raw instanceof CharacterAffiliationType || $raw instanceof CharacterAffiliationStance
                    ? $raw
                    : $newValue;
                $dirty = true;
            }

            if (! $dirty) {
                return $current;
            }

            $current->revision = $nextRevision;
            $current->save();

            return $current->refresh();
        });
    }

    public function end(CharacterAffiliation $affiliation, mixed $endedAt = null): CharacterAffiliation
    {
        $this->relations->end(WorldRelation::query()->findOrFail($affiliation->id), $endedAt);

        return $affiliation->refresh();
    }

    public function setSect(Character $character, ?WorldEntity $sect): ?CharacterAffiliation
    {
        if ($sect !== null) {
            $this->assertSect($character, $sect);
        }

        return $this->replaceSlot(
            $character,
            CharacterAffiliationType::Member,
            fn (CharacterAffiliation $row): bool => $this->isSectSlot($row),
            $sect,
            CharacterAffiliationStance::Allied,
            'member_of',
        );
    }

    public function setHaven(Character $character, ?WorldEntity $haven): ?CharacterAffiliation
    {
        if ($haven !== null) {
            $this->assertHaven($character, $haven);
        }

        return $this->replaceSlot(
            $character,
            CharacterAffiliationType::Resident,
            fn (CharacterAffiliation $row): bool => $row->target?->entity_type === WorldEntityType::Location,
            $haven,
            CharacterAffiliationStance::Neutral,
            'located_at',
        );
    }

    public function activeSect(Character $character): ?CharacterAffiliation
    {
        return $this->activeOfType($character, CharacterAffiliationType::Member)
            ->first(fn (CharacterAffiliation $row): bool => $this->isSectSlot($row));
    }

    public function activeHaven(Character $character): ?CharacterAffiliation
    {
        return $this->activeOfType($character, CharacterAffiliationType::Resident)
            ->first(fn (CharacterAffiliation $row): bool => $row->target?->entity_type === WorldEntityType::Location);
    }

    /**
     * @return Collection<int, CharacterAffiliation>
     */
    public function activeOfType(Character $character, CharacterAffiliationType $type): Collection
    {
        return CharacterAffiliation::query()
            ->where('character_id', $character->id)
            ->where('affiliation_type', $type)
            ->whereIn('id', WorldRelation::query()->active()->select('id'))
            ->with(['target.faction'])
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  callable(CharacterAffiliation): bool  $matchesSlot
     */
    private function replaceSlot(
        Character $character,
        CharacterAffiliationType $affiliationType,
        callable $matchesSlot,
        ?WorldEntity $target,
        CharacterAffiliationStance $stance,
        string $relationKey,
    ): ?CharacterAffiliation {
        return DB::transaction(function () use (
            $character,
            $affiliationType,
            $matchesSlot,
            $target,
            $stance,
            $relationKey,
        ): ?CharacterAffiliation {
            WorldRelation::query()
                ->where('source_entity_id', $character->id)
                ->active()
                ->lockForUpdate()
                ->get();

            $current = $this->activeOfType($character, $affiliationType)->filter($matchesSlot);

            if ($target === null) {
                foreach ($current as $row) {
                    $this->end($row);
                }

                return null;
            }

            $keep = $current->first(
                fn (CharacterAffiliation $row): bool => (int) $row->target_entity_id === (int) $target->id,
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

            return $this->attach(
                $character,
                $target,
                $affiliationType,
                $stance,
                WorldRelationType::query()->where('key', $relationKey)->firstOrFail(),
            );
        });
    }

    private function assertSect(Character $character, WorldEntity $sect): void
    {
        $this->assertAllowedTarget($character, $sect);

        if ($sect->entity_type !== WorldEntityType::Faction) {
            throw new InvalidArgumentException('A character sect must be a faction.');
        }

        if ($sect->status !== WorldEntityStatus::Active) {
            throw new InvalidArgumentException('A character sect must be an active faction.');
        }
    }

    private function assertHaven(Character $character, WorldEntity $haven): void
    {
        $this->assertAllowedTarget($character, $haven);

        if ($haven->entity_type !== WorldEntityType::Location) {
            throw new InvalidArgumentException('A character haven must be a location.');
        }

        if ($haven->status !== WorldEntityStatus::Active) {
            throw new InvalidArgumentException('A character haven must be an active location.');
        }
    }

    private function assertAllowedTarget(Character $character, WorldEntity $target): void
    {
        if ((int) $character->chronicle_id !== (int) $target->chronicle_id) {
            throw new MixedChronicleException;
        }

        $type = $target->entity_type instanceof WorldEntityType
            ? $target->entity_type
            : WorldEntityType::from((string) $target->entity_type);

        if (! in_array($type, self::TARGET_TYPES, true)) {
            throw new InvalidArgumentException(
                "Affiliation target must be a directory entity, not [{$type->value}].",
            );
        }
    }

    private function normalizeField(string $field, mixed $value): mixed
    {
        if ($field === 'affiliation_type') {
            $type = $value instanceof CharacterAffiliationType
                ? $value
                : CharacterAffiliationType::from((string) $value);

            return $type->value;
        }

        if ($field === 'stance') {
            $stance = $value instanceof CharacterAffiliationStance
                ? $value
                : CharacterAffiliationStance::from((string) $value);

            return $stance->value;
        }

        if (in_array($field, self::METRIC_FIELDS, true)) {
            if (! is_int($value)) {
                throw new InvalidArgumentException("Affiliation field [{$field}] must be an integer.");
            }

            $this->assertMetric($field, $value);

            return $value;
        }

        if (in_array($field, self::TEXT_FIELDS, true)) {
            return $this->normalizeText(is_string($value) || $value === null ? $value : (string) $value);
        }

        return $value;
    }

    private function export(mixed $value): mixed
    {
        if ($value instanceof CharacterAffiliationType || $value instanceof CharacterAffiliationStance) {
            return $value->value;
        }

        return $value;
    }

    private function assertMetric(string $field, int $value): void
    {
        if ($value < 0 || $value > 5) {
            throw new InvalidArgumentException("Affiliation field [{$field}] must be between 0 and 5.");
        }
    }

    private function normalizeText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim($value);

        return $text === '' ? null : $text;
    }

    private function isSectSlot(CharacterAffiliation $row): bool
    {
        if ($row->target?->entity_type !== WorldEntityType::Faction) {
            return false;
        }

        $relation = WorldRelation::query()->with('type')->find($row->id);

        return $relation?->type?->key === 'member_of';
    }
}
