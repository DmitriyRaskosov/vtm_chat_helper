<?php

namespace App\Lore;

use App\Enums\LoreAccessLevel;
use App\Enums\LoreEntryKind;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Models\Chronicle;
use App\Models\LoreEntry;
use App\Models\LoreEntryEntity;
use App\Models\LoreEntryVersion;
use App\Models\User;
use App\Models\WorldEntity;
use App\World\MixedChronicleException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class LoreEntryService
{
    /**
     * Replace the current lore entry and append an immutable version snapshot.
     * Does not touch the derived lore embedding corpus.
     *
     * @param  array<string, mixed>  $fields
     */
    public function publish(
        Chronicle $chronicle,
        array $fields,
        string $changeReason,
        ?LoreEntry $entry = null,
        ?User $author = null,
    ): LoreEntry {
        $changeReason = trim($changeReason);

        if ($changeReason === '') {
            throw new InvalidArgumentException('A change reason is required.');
        }

        foreach (array_keys($fields) as $field) {
            if (! in_array($field, ['title', 'kind', 'canonical_text', 'status', 'visibility', 'classification', 'situational', 'legacy_source_id'], true)) {
                throw new InvalidArgumentException("Unknown lore field [{$field}].");
            }
        }

        if ($entry !== null && (int) $entry->chronicle_id !== (int) $chronicle->id) {
            throw new MixedChronicleException;
        }

        if ($entry?->status === LoreEntryStatus::Archived) {
            throw new InvalidArgumentException('Archived lore cannot be published.');
        }

        return DB::transaction(function () use ($chronicle, $fields, $changeReason, $entry, $author): LoreEntry {
            if ($entry !== null) {
                $entry = LoreEntry::query()
                    ->where('id', $entry->id)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $merged = $this->merge($entry, $fields);

            if ($merged['status'] === LoreEntryStatus::Archived) {
                throw new InvalidArgumentException('Archive lore with archive().');
            }

            if ($merged['title'] === '' || $merged['canonical_text'] === '') {
                throw new InvalidArgumentException('Lore requires a title and canonical_text.');
            }

            if ($entry !== null && $this->sameCanon($entry, $merged)) {
                throw new InvalidArgumentException('Lore entry is unchanged.');
            }

            $nextVersion = $entry === null ? 1 : (int) $entry->current_version + 1;
            $status = $merged['status'];

            $payload = [
                'title' => $merged['title'],
                'kind' => $merged['kind'],
                'canonical_text' => $merged['canonical_text'],
                'status' => $status,
                'visibility' => $merged['visibility'],
                'classification' => $merged['classification'],
                'situational' => $merged['situational'],
                'current_version' => $nextVersion,
                'legacy_source_id' => $merged['legacy_source_id'],
                'approved_at' => $status === LoreEntryStatus::Approved ? now() : null,
                'approved_by' => $status === LoreEntryStatus::Approved ? $author?->id : null,
            ];

            if ($entry === null) {
                $entry = LoreEntry::query()->create([
                    'chronicle_id' => $chronicle->id,
                    'created_by' => $author?->id,
                    ...$payload,
                ]);
            } else {
                $entry->fill($payload);
                $entry->save();
            }

            LoreEntryVersion::query()->create([
                'lore_entry_id' => $entry->id,
                'chronicle_id' => $entry->chronicle_id,
                'version' => $nextVersion,
                'title' => $payload['title'],
                'kind' => $payload['kind'],
                'canonical_text' => $payload['canonical_text'],
                'status' => $payload['status'],
                'visibility' => $payload['visibility'],
                'classification' => $payload['classification'],
                'situational' => $payload['situational'],
                'change_reason' => $changeReason,
                'created_by' => $author?->id,
            ]);

            return $entry->refresh();
        });
    }

    public function approve(LoreEntry $entry, User $approver): LoreEntry
    {
        return DB::transaction(function () use ($entry, $approver): LoreEntry {
            $entry = LoreEntry::query()
                ->where('id', $entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->status === LoreEntryStatus::Archived) {
                throw new InvalidArgumentException('Archived lore cannot be approved.');
            }

            if ($entry->status === LoreEntryStatus::Approved
                && (int) $entry->approved_by === (int) $approver->id) {
                return $entry;
            }

            $entry->status = LoreEntryStatus::Approved;
            $entry->approved_at = now();
            $entry->approved_by = $approver->id;
            $entry->save();

            return $entry->refresh();
        });
    }

    public function archive(LoreEntry $entry): LoreEntry
    {
        return DB::transaction(function () use ($entry): LoreEntry {
            $entry = LoreEntry::query()
                ->where('id', $entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->status === LoreEntryStatus::Archived) {
                return $entry;
            }

            $entry->status = LoreEntryStatus::Archived;
            $entry->save();

            return $entry->refresh();
        });
    }

    public function restore(LoreEntry $entry): LoreEntry
    {
        return DB::transaction(function () use ($entry): LoreEntry {
            $entry = LoreEntry::query()
                ->where('id', $entry->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($entry->status !== LoreEntryStatus::Archived) {
                return $entry;
            }

            $entry->status = LoreEntryStatus::Approved;
            $entry->approved_at = $entry->approved_at ?? now();
            $entry->save();

            return $entry->refresh();
        });
    }

    /**
     * @param  list<int>  $entityIds
     */
    public function syncEntities(LoreEntry $entry, array $entityIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $entityIds)));

        DB::transaction(function () use ($entry, $ids): void {
            $entities = WorldEntity::query()->whereIn('id', $ids)->get()->keyBy('id');

            foreach ($ids as $id) {
                $entity = $entities->get($id);
                if ($entity === null) {
                    throw new InvalidArgumentException('Unknown world entity.');
                }

                $this->attachEntity($entry, $entity);
            }

            LoreEntryEntity::query()
                ->where('lore_entry_id', $entry->id)
                ->when(
                    $ids !== [],
                    fn ($query) => $query->whereNotIn('entity_id', $ids),
                    fn ($query) => $query,
                )
                ->delete();
        });
    }

    public function attachEntity(LoreEntry $entry, WorldEntity $entity, ?string $role = null): LoreEntryEntity
    {
        if ((int) $entry->chronicle_id !== (int) $entity->chronicle_id) {
            throw new MixedChronicleException;
        }

        $role = $role !== null ? trim($role) : null;
        if ($role === '') {
            $role = null;
        }

        return DB::transaction(function () use ($entry, $entity, $role): LoreEntryEntity {
            $existing = LoreEntryEntity::query()
                ->where('lore_entry_id', $entry->id)
                ->where('entity_id', $entity->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                $existing->role = $role;
                $existing->save();

                return $existing->refresh();
            }

            return LoreEntryEntity::query()->create([
                'lore_entry_id' => $entry->id,
                'entity_id' => $entity->id,
                'chronicle_id' => $entry->chronicle_id,
                'role' => $role,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{
     *     title: string,
     *     kind: LoreEntryKind,
     *     canonical_text: string,
     *     status: LoreEntryStatus,
     *     visibility: LoreVisibility,
     *     classification: LoreAccessLevel,
     *     situational: bool,
     *     legacy_source_id: ?string
     * }
     */
    private function merge(?LoreEntry $entry, array $fields): array
    {
        $merged = [
            'title' => $entry?->title ?? '',
            'kind' => $entry?->kind ?? LoreEntryKind::Custom,
            'canonical_text' => $entry?->canonical_text ?? '',
            'status' => $entry?->status ?? LoreEntryStatus::Draft,
            'visibility' => $entry?->visibility ?? LoreVisibility::Public,
            'classification' => $entry?->classification ?? LoreAccessLevel::L0,
            'situational' => (bool) ($entry?->situational ?? false),
            'legacy_source_id' => $entry?->legacy_source_id,
        ];

        if (array_key_exists('title', $fields)) {
            $merged['title'] = trim((string) $fields['title']);
        }

        if (array_key_exists('canonical_text', $fields)) {
            $merged['canonical_text'] = trim((string) $fields['canonical_text']);
        }

        if (array_key_exists('kind', $fields)) {
            $merged['kind'] = $fields['kind'] instanceof LoreEntryKind
                ? $fields['kind']
                : LoreEntryKind::from((string) $fields['kind']);
        }

        if (array_key_exists('status', $fields)) {
            $merged['status'] = $fields['status'] instanceof LoreEntryStatus
                ? $fields['status']
                : LoreEntryStatus::from((string) $fields['status']);
        }

        if (array_key_exists('visibility', $fields)) {
            $merged['visibility'] = $fields['visibility'] instanceof LoreVisibility
                ? $fields['visibility']
                : LoreVisibility::from((string) $fields['visibility']);
        }

        if (array_key_exists('classification', $fields)) {
            $merged['classification'] = $fields['classification'] instanceof LoreAccessLevel
                ? $fields['classification']
                : LoreAccessLevel::from((string) $fields['classification']);
        }

        if (array_key_exists('situational', $fields)) {
            $merged['situational'] = (bool) $fields['situational'];
        }

        if (array_key_exists('legacy_source_id', $fields)) {
            $legacy = $fields['legacy_source_id'];
            $legacy = $legacy === null ? null : trim((string) $legacy);
            $merged['legacy_source_id'] = $legacy === '' ? null : $legacy;
        }

        return $merged;
    }

    /**
     * @param  array{
     *     title: string,
     *     kind: LoreEntryKind,
     *     canonical_text: string,
     *     status: LoreEntryStatus,
     *     visibility: LoreVisibility,
     *     classification: LoreAccessLevel,
     *     situational: bool,
     *     legacy_source_id: ?string
     * }  $merged
     */
    private function sameCanon(LoreEntry $entry, array $merged): bool
    {
        return $entry->title === $merged['title']
            && $entry->kind === $merged['kind']
            && $entry->canonical_text === $merged['canonical_text']
            && $entry->status === $merged['status']
            && $entry->visibility === $merged['visibility']
            && $entry->classification === $merged['classification']
            && (bool) $entry->situational === $merged['situational']
            && $entry->legacy_source_id === $merged['legacy_source_id'];
    }
}
