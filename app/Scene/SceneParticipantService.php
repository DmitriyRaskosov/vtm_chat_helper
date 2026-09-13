<?php

namespace App\Scene;

use App\Enums\SceneParticipantRole;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\World\MixedChronicleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SceneParticipantService
{
    public function __construct(private SceneContextService $contexts) {}

    public function enter(
        Scene $scene,
        Character $character,
        SceneParticipantRole $role,
        bool $visible = true,
    ): SceneParticipant {
        $scene->loadMissing('gameSession');

        if ((int) $character->chronicle_id !== (int) $scene->gameSession->chronicle_id) {
            throw new MixedChronicleException;
        }

        if (! $character->is_active) {
            throw new InvalidArgumentException('An archived character cannot enter a scene.');
        }

        return DB::transaction(function () use ($scene, $character, $role, $visible): SceneParticipant {
            $lockedScene = Scene::query()->lockForUpdate()->findOrFail($scene->id);
            $this->contexts->assertMutable($lockedScene);

            $current = SceneParticipant::query()
                ->where('scene_id', $lockedScene->id)
                ->where('character_id', $character->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();

            if ($current !== null) {
                return $current;
            }

            return SceneParticipant::query()->create([
                'scene_id' => $lockedScene->id,
                'chronicle_id' => $character->chronicle_id,
                'character_id' => $character->id,
                'role' => $role,
                'visible' => $visible,
                'is_current' => true,
                'entered_at' => now(),
            ])->refresh();
        });
    }

    public function leave(Scene $scene, Character $character): SceneParticipant
    {
        return DB::transaction(function () use ($scene, $character): SceneParticipant {
            $lockedScene = Scene::query()->lockForUpdate()->findOrFail($scene->id);
            $this->contexts->assertMutable($lockedScene);

            $current = SceneParticipant::query()
                ->where('scene_id', $lockedScene->id)
                ->where('character_id', $character->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                throw new InvalidArgumentException('Character is not a current participant of this scene.');
            }

            $current->is_current = false;
            $current->left_at = now();
            $current->save();

            return $current->refresh();
        });
    }

    public function leaveMutableScenes(Character $character): void
    {
        $rows = SceneParticipant::query()
            ->where('character_id', $character->id)
            ->where('is_current', true)
            ->get();

        foreach ($rows as $row) {
            $scene = Scene::query()->find($row->scene_id);
            if ($scene === null) {
                continue;
            }

            try {
                $this->leave($scene, $character);
            } catch (SceneFrozenException|InvalidArgumentException) {
                continue;
            }
        }
    }

    /**
     * @return Collection<int, SceneParticipant>
     */
    public function list(Scene $scene): Collection
    {
        return SceneParticipant::query()
            ->where('scene_id', $scene->id)
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, SceneParticipant>
     */
    public function current(Scene $scene): Collection
    {
        return SceneParticipant::query()
            ->where('scene_id', $scene->id)
            ->where('is_current', true)
            ->orderBy('id')
            ->get();
    }
}
