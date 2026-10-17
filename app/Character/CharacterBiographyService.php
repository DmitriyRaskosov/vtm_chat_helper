<?php

namespace App\Character;

use App\Character\Exceptions\BiographyUnchangedException;
use App\Character\Exceptions\InvalidBiographyInputException;
use App\Enums\CharacterBiographyStatus;
use App\Models\Character;
use App\Models\CharacterBiography;
use App\Models\CharacterBiographyVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
     * Заменяет текущую биографию и добавляет неизменяемый снимок версии.
     * Поисковый индекс не трогает.
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
            throw new InvalidBiographyInputException('A change reason is required.');
        }

        foreach (array_keys($fields) as $field) {
            if (! in_array($field, [...self::TEXT_FIELDS, 'status'], true)) {
                throw new InvalidBiographyInputException("Unknown biography field [{$field}].");
            }
        }

        return DB::transaction(function () use ($character, $fields, $changeReason, $author): CharacterBiography {
            $biography = CharacterBiography::query()
                ->where('character_id', $character->id)
                ->lockForUpdate()
                ->first();

            $merged = $this->merge($biography, $fields);

            if ($this->isBlank($merged['summary']) && $this->isBlank($merged['full_text'])) {
                throw new InvalidBiographyInputException('Biography requires a summary or full_text.');
            }

            if ($biography !== null && $this->sameCanon($biography, $merged)) {
                throw new BiographyUnchangedException;
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
