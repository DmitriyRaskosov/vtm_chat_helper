<?php

namespace App\Character;

use App\Enums\CharacterHealthState;
use App\Enums\CharacterStatusEffectType;
use App\Models\Character;
use App\Models\CharacterStatus;
use App\Models\CharacterStatusChange;
use App\Models\CharacterStatusEffect;
use App\Models\Location;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\World\MixedChronicleException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CharacterStatusService
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function apply(
        Character $character,
        array $fields,
        int $expectedRevision,
        ?string $reason = null,
        ?Scene $scene = null,
        ?Message $message = null,
        ?User $changedBy = null,
        ?string $gameTime = null,
    ): CharacterStatus {
        $allowed = [
            'temporary_willpower',
            'blood_pool',
            'hunger',
            'health_state',
            'fatigue',
            'current_location_id',
        ];

        foreach (array_keys($fields) as $field) {
            if (! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException("Unknown character status field [{$field}].");
            }
        }

        return DB::transaction(function () use (
            $character,
            $fields,
            $expectedRevision,
            $reason,
            $scene,
            $message,
            $changedBy,
            $gameTime,
        ): CharacterStatus {
            $status = CharacterStatus::query()
                ->where('character_id', $character->id)
                ->lockForUpdate()
                ->first();

            if ($status === null) {
                if ($expectedRevision !== 0) {
                    throw new CharacterStatusRevisionException($expectedRevision, 0);
                }

                $status = CharacterStatus::query()->create([
                    'character_id' => $character->id,
                    'chronicle_id' => $character->chronicle_id,
                    'revision' => 0,
                ])->refresh();
            } elseif ($status->revision !== $expectedRevision) {
                throw new CharacterStatusRevisionException($expectedRevision, (int) $status->revision);
            }

            $nextRevision = (int) $status->revision + 1;
            $dirty = false;

            foreach ($fields as $field => $raw) {
                $newValue = $this->normalize($character, $field, $raw);
                $oldValue = $this->export($status->{$field});

                if ($oldValue === $newValue) {
                    continue;
                }

                CharacterStatusChange::query()->create([
                    'character_id' => $character->id,
                    'field' => $field,
                    'old_value' => ['v' => $oldValue],
                    'new_value' => ['v' => $newValue],
                    'reason' => $reason,
                    'scene_id' => $scene?->id,
                    'message_id' => $message?->id,
                    'game_time' => $gameTime,
                    'changed_by' => $changedBy?->id,
                    'revision' => $nextRevision,
                ]);

                $status->{$field} = $raw instanceof CharacterHealthState ? $raw : $newValue;
                $dirty = true;
            }

            if (! $dirty) {
                return $status;
            }

            $status->revision = $nextRevision;
            $status->save();

            return $status->refresh();
        });
    }

    /**
     * @param  array<string, mixed>|null  $modifier
     */
    public function addEffect(
        Character $character,
        CharacterStatusEffectType $type,
        string $description,
        ?array $modifier = null,
        mixed $activeFrom = null,
        mixed $activeUntil = null,
        ?string $sourceType = null,
        ?int $sourceId = null,
    ): CharacterStatusEffect {
        $description = trim($description);

        if ($description === '') {
            throw new InvalidArgumentException('An effect description is required.');
        }

        return CharacterStatusEffect::query()->create([
            'character_id' => $character->id,
            'effect_type' => $type,
            'description' => $description,
            'modifier' => $modifier,
            'active_from' => $activeFrom,
            'active_until' => $activeUntil,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'is_active' => true,
        ]);
    }

    public function deactivateEffect(CharacterStatusEffect $effect): CharacterStatusEffect
    {
        $effect->update(['is_active' => false]);

        return $effect->refresh();
    }

    public function current(Character $character): ?CharacterStatus
    {
        return CharacterStatus::query()->find($character->id);
    }

    private function normalize(Character $character, string $field, mixed $value): mixed
    {
        if ($field === 'health_state') {
            $state = $value instanceof CharacterHealthState
                ? $value
                : CharacterHealthState::from((string) $value);

            return $state->value;
        }

        if ($field === 'current_location_id') {
            if ($value === null) {
                return null;
            }

            $location = $value instanceof Location
                ? $value
                : Location::query()->findOrFail((int) $value);

            if ((int) $location->chronicle_id !== (int) $character->chronicle_id) {
                throw new MixedChronicleException;
            }

            return (int) $location->id;
        }

        if (! is_int($value) || $value < 0) {
            throw new InvalidArgumentException("Status field [{$field}] must be a non-negative integer.");
        }

        return $value;
    }

    private function export(mixed $value): mixed
    {
        if ($value instanceof CharacterHealthState) {
            return $value->value;
        }

        return $value;
    }
}
