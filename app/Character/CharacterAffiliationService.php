<?php

namespace App\Character;

use App\Enums\CharacterAffiliationStance;
use App\Enums\CharacterAffiliationType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\CharacterAffiliation;
use App\Models\CharacterAffiliationChange;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use App\Models\WorldRelationType;
use App\World\MixedChronicleException;
use App\World\WorldRelationService;
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
                "Affiliation target must be a faction, location, item, or concept, not [{$type->value}].",
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
}
