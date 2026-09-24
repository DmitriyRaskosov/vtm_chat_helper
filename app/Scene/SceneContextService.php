<?php

namespace App\Scene;

use App\Enums\SceneStatus;
use App\Models\Location;
use App\Models\Scene;
use App\Models\SceneContext;
use App\Models\User;
use App\World\MixedChronicleException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SceneContextService
{
    /**
     * @param  array<string, mixed>  $fields
     */
    public function apply(
        Scene $scene,
        array $fields,
        int $expectedRevision,
        ?User $updatedBy = null,
    ): SceneContext {
        $allowed = [
            'location_entity_id',
            'atmosphere',
            'situation',
            'storyteller_notes',
        ];

        foreach (array_keys($fields) as $field) {
            if (! in_array($field, $allowed, true)) {
                throw new InvalidArgumentException("Unknown scene context field [{$field}].");
            }
        }

        $scene->loadMissing('gameSession');
        $chronicleId = (int) $scene->gameSession->chronicle_id;

        return DB::transaction(function () use ($scene, $fields, $expectedRevision, $updatedBy, $chronicleId): SceneContext {
            $lockedScene = Scene::query()->lockForUpdate()->findOrFail($scene->id);
            $this->assertMutable($lockedScene);

            $context = SceneContext::query()
                ->where('scene_id', $lockedScene->id)
                ->lockForUpdate()
                ->first();

            if ($context === null) {
                if ($expectedRevision !== 0) {
                    throw new SceneContextRevisionException($expectedRevision, 0);
                }

                $context = SceneContext::query()->create([
                    'scene_id' => $lockedScene->id,
                    'chronicle_id' => $chronicleId,
                    'revision' => 0,
                ])->refresh();
            } elseif ((int) $context->revision !== $expectedRevision) {
                throw new SceneContextRevisionException($expectedRevision, (int) $context->revision);
            }

            if ($context->frozen_revision !== null) {
                throw new SceneFrozenException;
            }

            $nextRevision = (int) $context->revision + 1;
            $dirty = false;

            foreach ($fields as $field => $raw) {
                $newValue = $this->normalize($chronicleId, $field, $raw);
                $oldValue = $context->{$field};

                if ($oldValue === $newValue) {
                    continue;
                }

                $context->{$field} = $newValue;
                $dirty = true;
            }

            if (! $dirty) {
                return $context;
            }

            $context->revision = $nextRevision;
            $context->updated_by = $updatedBy?->id;
            $context->save();

            return $context->refresh();
        });
    }

    public function current(Scene $scene): ?SceneContext
    {
        return SceneContext::query()->where('scene_id', $scene->id)->first();
    }

    public function freeze(Scene $scene): void
    {
        DB::transaction(function () use ($scene): void {
            $context = SceneContext::query()
                ->where('scene_id', $scene->id)
                ->lockForUpdate()
                ->first();

            if ($context === null || $context->frozen_revision !== null) {
                return;
            }

            $context->frozen_revision = $context->revision;
            $context->save();
        });
    }

    /**
     * @param  list<int>  $sceneIds
     */
    public function freezeMany(array $sceneIds): void
    {
        $sceneIds = array_values(array_unique(array_map('intval', $sceneIds)));
        if ($sceneIds === []) {
            return;
        }

        SceneContext::query()
            ->whereIn('scene_id', $sceneIds)
            ->whereNull('frozen_revision')
            ->update([
                'frozen_revision' => DB::raw('revision'),
                'updated_at' => now(),
            ]);
    }

    public function assertMutable(Scene $scene): void
    {
        if ($scene->status === SceneStatus::Closed) {
            throw new SceneFrozenException;
        }

        $frozen = SceneContext::query()
            ->where('scene_id', $scene->id)
            ->whereNotNull('frozen_revision')
            ->exists();

        if ($frozen) {
            throw new SceneFrozenException;
        }
    }

    private function normalize(int $chronicleId, string $field, mixed $raw): mixed
    {
        if ($field === 'location_entity_id') {
            if ($raw === null || $raw === '') {
                return null;
            }

            $locationId = (int) $raw;
            $location = Location::query()->find($locationId);
            if ($location === null) {
                throw new InvalidArgumentException('Scene location must be a location entity.');
            }
            if ((int) $location->chronicle_id !== $chronicleId) {
                throw new MixedChronicleException;
            }

            return $locationId;
        }

        if ($raw === null) {
            return null;
        }

        $text = trim((string) $raw);

        return $text === '' ? null : $text;
    }
}
