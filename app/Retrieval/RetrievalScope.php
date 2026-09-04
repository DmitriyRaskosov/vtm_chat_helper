<?php

namespace App\Retrieval;

use App\Models\Scene;

final readonly class RetrievalScope
{
    public function __construct(
        public int $gameSessionId,
        public int $activeSceneId,
    ) {}

    public static function fromScene(Scene $scene): self
    {
        return new self(
            (int) $scene->game_session_id,
            (int) $scene->id,
        );
    }

    public function sceneIdInSession(?int $sceneId): ?int
    {
        if ($sceneId === null) {
            return null;
        }

        $belongs = Scene::query()
            ->whereKey($sceneId)
            ->where('game_session_id', $this->gameSessionId)
            ->exists();

        return $belongs ? $sceneId : null;
    }
}
