<?php

namespace App\Character;

use App\Enums\CharacterBiographyStatus;
use App\Models\Character;
use App\Models\CharacterBiography;
use App\Models\CharacterBiographyVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CharacterBiographyService
{
    private const TEXT_FIELDS = [
        'summary',
        'full_text',
        'principles',
        'motivation',
        'fears',
        'desires',
        'behavioral_rules',
    ];

    /**
     * Replace the current biography and append an immutable version snapshot.
     * Does not touch the search index.
     *
     * @param  array<string, mixed>  $fields
     */
    public function publish(
        Character $character,
        array $fields,
        string $changeReason,
        ?User $author = null,
    ): CharacterBiography {
        $changeReason = trim($changeReason);

        if ($changeReason === '') {
            throw new InvalidArgumentException('A change reason is required.');
        }

        foreach (array_keys($fields) as $field) {
            if (! in_array($field, [...self::TEXT_FIELDS, 'status'], true)) {
                throw new InvalidArgumentException("Unknown biography field [{$field}].");
            }
        }

        return DB::transaction(function () use ($character, $fields, $changeReason, $author): CharacterBiography {
            $biography = CharacterBiography::query()
                ->where('character_id', $character->id)
                ->lockForUpdate()
                ->first();

            $merged = $this->merge($biography, $fields);

            if ($this->isBlank($merged['summary']) && $this->isBlank($merged['full_text'])) {
                throw new InvalidArgumentException('Biography requires a summary or full_text.');
            }

            if ($biography !== null && $this->sameCanon($biography, $merged)) {
                throw new InvalidArgumentException('Biography is unchanged.');
            }

            $nextVersion = $biography === null ? 1 : (int) $biography->current_version + 1;
            $status = $merged['status'];

            $payload = [
                'summary' => $merged['summary'],
                'full_text' => $merged['full_text'],
                'principles' => $merged['principles'],
                'motivation' => $merged['motivation'],
                'fears' => $merged['fears'],
                'desires' => $merged['desires'],
                'behavioral_rules' => $merged['behavioral_rules'],
                'current_version' => $nextVersion,
                'status' => $status,
                'approved_at' => $status === CharacterBiographyStatus::Approved ? now() : null,
                'approved_by' => $status === CharacterBiographyStatus::Approved ? $author?->id : null,
            ];

            if ($biography === null) {
                $biography = CharacterBiography::query()->create([
                    'character_id' => $character->id,
                    ...$payload,
                ]);
            } else {
                $biography->fill($payload);
                $biography->save();
            }

            CharacterBiographyVersion::query()->create([
                'character_id' => $character->id,
                'version' => $nextVersion,
                'summary' => $payload['summary'],
                'full_text' => $payload['full_text'],
                'principles' => $payload['principles'],
                'motivation' => $payload['motivation'],
                'fears' => $payload['fears'],
                'desires' => $payload['desires'],
                'behavioral_rules' => $payload['behavioral_rules'],
                'change_reason' => $changeReason,
                'created_by' => $author?->id,
            ]);

            return $biography->refresh();
        });
    }

    public function approve(Character $character, User $approver): CharacterBiography
    {
        return DB::transaction(function () use ($character, $approver): CharacterBiography {
            $biography = CharacterBiography::query()
                ->where('character_id', $character->id)
                ->lockForUpdate()
                ->first();

            if ($biography === null) {
                throw new InvalidArgumentException('Character has no biography to approve.');
            }

            if ($biography->status === CharacterBiographyStatus::Approved
                && (int) $biography->approved_by === (int) $approver->id) {
                return $biography;
            }

            $biography->status = CharacterBiographyStatus::Approved;
            $biography->approved_at = now();
            $biography->approved_by = $approver->id;
            $biography->save();

            return $biography->refresh();
        });
    }

    public function current(Character $character): ?CharacterBiography
    {
        return CharacterBiography::query()->find($character->id);
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array{
     *     summary: ?string,
     *     full_text: ?string,
     *     principles: ?string,
     *     motivation: ?string,
     *     fears: ?string,
     *     desires: ?string,
     *     behavioral_rules: ?string,
     *     status: CharacterBiographyStatus
     * }
     */
    private function merge(?CharacterBiography $biography, array $fields): array
    {
        $merged = [
            'summary' => $biography?->summary,
            'full_text' => $biography?->full_text,
            'principles' => $biography?->principles,
            'motivation' => $biography?->motivation,
            'fears' => $biography?->fears,
            'desires' => $biography?->desires,
            'behavioral_rules' => $biography?->behavioral_rules,
            'status' => $biography?->status ?? CharacterBiographyStatus::Draft,
        ];

        foreach (self::TEXT_FIELDS as $field) {
            if (array_key_exists($field, $fields)) {
                $merged[$field] = $this->normalizeText($fields[$field]);
            }
        }

        if (array_key_exists('status', $fields)) {
            $merged['status'] = $fields['status'] instanceof CharacterBiographyStatus
                ? $fields['status']
                : CharacterBiographyStatus::from((string) $fields['status']);
        }

        return $merged;
    }

    /**
     * @param  array{
     *     summary: ?string,
     *     full_text: ?string,
     *     principles: ?string,
     *     motivation: ?string,
     *     fears: ?string,
     *     desires: ?string,
     *     behavioral_rules: ?string,
     *     status: CharacterBiographyStatus
     * }  $merged
     */
    private function sameCanon(CharacterBiography $biography, array $merged): bool
    {
        foreach (self::TEXT_FIELDS as $field) {
            if ($biography->{$field} !== $merged[$field]) {
                return false;
            }
        }

        return $biography->status === $merged['status'];
    }

    private function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : $text;
    }

    private function isBlank(?string $value): bool
    {
        return $value === null || $value === '';
    }
}
