<?php

namespace App\Scene;

use App\Chronicle\CurrentChronicle;
use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Models\Scene;
use App\Scene\Exceptions\CopilotRequiresActiveSceneException;
use App\Scene\Exceptions\InactiveSceneForMessagesException;
use App\Scene\Exceptions\NoAvailableSceneException;

final class ActiveSceneResolver
{
    public function __construct(private CurrentChronicle $currentChronicle) {}

    public function resolve(?int $sceneId): Scene
    {
        $scene = $this->findInActiveSession($sceneId);

        if ($scene === null) {
            throw new NoAvailableSceneException;
        }

        return $scene;
    }

    public function resolveActive(?int $sceneId, bool $forCopilot = false): Scene
    {
        $scene = $this->findInActiveSession($sceneId);

        if ($forCopilot) {
            if ($scene === null || $scene->status !== SceneStatus::Active) {
                throw new CopilotRequiresActiveSceneException;
            }

            return $scene;
        }

        if ($scene === null) {
            throw new NoAvailableSceneException;
        }

        if ($scene->status !== SceneStatus::Active) {
            throw new InactiveSceneForMessagesException;
        }

        return $scene;
    }

    public function activeOrNull(): ?Scene
    {
        $chronicleId = $this->currentChronicle->id();

        return Scene::query()
            ->active()
            ->whereHas('gameSession', function ($query) use ($chronicleId) {
                $query->active()->where('chronicle_id', $chronicleId);
            })
            ->first();
    }

    private function findInActiveSession(?int $sceneId): ?Scene
    {
        $chronicleId = $this->currentChronicle->id();

        $query = Scene::query()->whereHas(
            'gameSession',
            function ($query) use ($chronicleId) {
                $query->where('status', GameSessionStatus::Active)
                    ->where('chronicle_id', $chronicleId);
            },
        );

        if ($sceneId !== null) {
            $query->whereKey($sceneId);
        } else {
            $query->active();
        }

        return $query->first();
    }
}
