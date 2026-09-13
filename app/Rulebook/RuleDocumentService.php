<?php

namespace App\Rulebook;

use App\Enums\CharacterStatCategory;
use App\Enums\CharacterStatusEffectType;
use App\Enums\RuleDocumentStatus;
use App\Models\Chronicle;
use App\Models\ChronicleRuleOverride;
use App\Models\Discipline;
use App\Models\DisciplinePower;
use App\Models\RuleDocument;
use App\Models\RuleDocumentEffectType;
use App\Models\RuleDocumentStatKey;
use App\Models\RuleDocumentVersion;
use App\Models\Ruleset;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RuleDocumentService
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function publish(
        Ruleset $ruleset,
        array $fields,
        string $changeReason,
        ?RuleDocument $document = null,
        ?User $author = null,
    ): RuleDocument {
        $changeReason = trim($changeReason);

        if ($changeReason === '') {
            throw new InvalidArgumentException('A change reason is required.');
        }

        foreach (array_keys($fields) as $field) {
            if (! in_array($field, ['title', 'section', 'canonical_text', 'source_reference', 'status'], true)) {
                throw new InvalidArgumentException("Unknown rule field [{$field}].");
            }
        }

        if ($document !== null && (int) $document->ruleset_id !== (int) $ruleset->id) {
            throw new InvalidArgumentException('Rule document does not belong to this ruleset.');
        }

        if ($document?->status === RuleDocumentStatus::Archived) {
            throw new InvalidArgumentException('Archived rule documents cannot be published.');
        }

        return DB::transaction(function () use ($ruleset, $fields, $changeReason, $document, $author): RuleDocument {
            if ($document !== null) {
                $document = RuleDocument::query()
                    ->where('id', $document->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $merged = $this->merge($document, $fields);

            if ($merged['status'] === RuleDocumentStatus::Archived) {
                throw new InvalidArgumentException('Archive rule documents with archive().');
            }

            if ($merged['title'] === '' || $merged['section'] === '' || $merged['canonical_text'] === '') {
                throw new InvalidArgumentException('Rule document requires title, section and canonical_text.');
            }

            if ($document !== null && $this->sameCanon($document, $merged)) {
                throw new InvalidArgumentException('Rule document is unchanged.');
            }

            $nextVersion = $document === null ? 1 : (int) $document->current_version + 1;
            $status = $merged['status'];

            $payload = [
                'title' => $merged['title'],
                'section' => $merged['section'],
                'canonical_text' => $merged['canonical_text'],
                'source_reference' => $merged['source_reference'],
                'current_version' => $nextVersion,
                'status' => $status,
                'approved_at' => $status === RuleDocumentStatus::Approved ? now() : null,
                'approved_by' => $status === RuleDocumentStatus::Approved ? $author?->id : null,
            ];

            if ($document === null) {
                $document = RuleDocument::query()->create([
                    'ruleset_id' => $ruleset->id,
                    'created_by' => $author?->id,
                    ...$payload,
                ]);
            } else {
                $document->fill($payload);
                $document->save();
            }

            RuleDocumentVersion::query()->create([
                'rule_document_id' => $document->id,
                'version' => $nextVersion,
                'title' => $payload['title'],
                'section' => $payload['section'],
                'canonical_text' => $payload['canonical_text'],
                'source_reference' => $payload['source_reference'],
                'status' => $payload['status'],
                'change_reason' => $changeReason,
                'created_by' => $author?->id,
            ]);

            return $document->refresh();
        });
    }

    public function archive(RuleDocument $document): RuleDocument
    {
        return DB::transaction(function () use ($document): RuleDocument {
            $document = RuleDocument::query()
                ->where('id', $document->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($document->status === RuleDocumentStatus::Archived) {
                return $document;
            }

            $document->status = RuleDocumentStatus::Archived;
            $document->save();

            return $document->refresh();
        });
    }

    public function linkDiscipline(RuleDocument $document, Discipline $discipline): void
    {
        $document->disciplines()->syncWithoutDetaching([$discipline->id]);
    }

    public function linkPower(RuleDocument $document, DisciplinePower $power): void
    {
        $document->powers()->syncWithoutDetaching([$power->id]);
    }

    public function linkStatKey(RuleDocument $document, CharacterStatCategory $category, string $statKey): RuleDocumentStatKey
    {
        $statKey = trim($statKey);

        if ($statKey === '') {
            throw new InvalidArgumentException('A stat key is required.');
        }

        return RuleDocumentStatKey::query()->firstOrCreate([
            'rule_document_id' => $document->id,
            'stat_category' => $category,
            'stat_key' => $statKey,
        ]);
    }

    public function linkEffectType(RuleDocument $document, CharacterStatusEffectType $effectType): RuleDocumentEffectType
    {
        return RuleDocumentEffectType::query()->firstOrCreate([
            'rule_document_id' => $document->id,
            'effect_type' => $effectType,
        ]);
    }

    public function override(
        Chronicle $chronicle,
        RuleDocument $document,
        string $overrideText,
        string $changeReason,
        RuleDocumentStatus $status = RuleDocumentStatus::Approved,
        ?User $author = null,
    ): ChronicleRuleOverride {
        $overrideText = trim($overrideText);
        $changeReason = trim($changeReason);

        if ($overrideText === '' || $changeReason === '') {
            throw new InvalidArgumentException('Override text and change reason are required.');
        }

        return DB::transaction(function () use ($chronicle, $document, $overrideText, $changeReason, $status, $author): ChronicleRuleOverride {
            $existing = ChronicleRuleOverride::query()
                ->where('chronicle_id', $chronicle->id)
                ->where('rule_document_id', $document->id)
                ->lockForUpdate()
                ->first();

            $payload = [
                'override_text' => $overrideText,
                'status' => $status,
                'change_reason' => $changeReason,
                'approved_at' => $status === RuleDocumentStatus::Approved ? now() : null,
                'approved_by' => $status === RuleDocumentStatus::Approved ? $author?->id : null,
            ];

            if ($existing === null) {
                return ChronicleRuleOverride::query()->create([
                    'chronicle_id' => $chronicle->id,
                    'rule_document_id' => $document->id,
                    'current_version' => 1,
                    'created_by' => $author?->id,
                    ...$payload,
                ]);
            }

            $existing->fill([
                ...$payload,
                'current_version' => (int) $existing->current_version + 1,
            ]);
            $existing->save();

            return $existing->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{
     *     title: string,
     *     section: string,
     *     canonical_text: string,
     *     source_reference: ?string,
     *     status: RuleDocumentStatus
     * }
     */
    private function merge(?RuleDocument $document, array $fields): array
    {
        $merged = [
            'title' => $document?->title ?? '',
            'section' => $document?->section ?? '',
            'canonical_text' => $document?->canonical_text ?? '',
            'source_reference' => $document?->source_reference,
            'status' => $document?->status ?? RuleDocumentStatus::Draft,
        ];

        foreach (['title', 'section', 'canonical_text'] as $field) {
            if (array_key_exists($field, $fields)) {
                $merged[$field] = trim((string) $fields[$field]);
            }
        }

        if (array_key_exists('source_reference', $fields)) {
            $ref = $fields['source_reference'];
            $ref = $ref === null ? null : trim((string) $ref);
            $merged['source_reference'] = $ref === '' ? null : $ref;
        }

        if (array_key_exists('status', $fields)) {
            $merged['status'] = $fields['status'] instanceof RuleDocumentStatus
                ? $fields['status']
                : RuleDocumentStatus::from((string) $fields['status']);
        }

        return $merged;
    }

    /**
     * @param  array{
     *     title: string,
     *     section: string,
     *     canonical_text: string,
     *     source_reference: ?string,
     *     status: RuleDocumentStatus
     * }  $merged
     */
    private function sameCanon(RuleDocument $document, array $merged): bool
    {
        return $document->title === $merged['title']
            && $document->section === $merged['section']
            && $document->canonical_text === $merged['canonical_text']
            && $document->source_reference === $merged['source_reference']
            && $document->status === $merged['status'];
    }
}
