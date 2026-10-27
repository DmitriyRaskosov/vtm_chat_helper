<?php

namespace App\Scene;

use App\Diary\DiaryScheduler;
use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Models\GameSession;
use App\Models\Scene;
use App\Scene\Exceptions\InactiveGameSessionForSceneException;
use Illuminate\Support\Facades\DB;

final class SceneLifecycleService
{
    public function __construct(
        private SceneContextService $contexts,
        private DiaryScheduler $diaryScheduler,
    ) {}

    public function create(
        GameSession $gameSession,
        string $title,
        ?string $description,
        bool $activate,
    ): Scene {
        return DB::transaction(function () use ($gameSession, $title, $description, $activate): Scene {
            $session = GameSession::query()->lockForUpdate()->findOrFail($gameSession->id);

            if ($session->status !== GameSessionStatus::Active) {
                throw new InactiveGameSessionForSceneException(
                    'Scenes can only be added to the active game session.',
                );
            }

            $now = now();

            if ($activate) {
                $session->scenes()
                    ->active()
                    ->update([
                        'status' => SceneStatus::Draft,
                        'updated_at' => $now,
                    ]);
            }

            $position = ((int) $session->scenes()->max('position')) + 1;

            return $session->scenes()->create([
                'position' => $position,
                'title' => $title,
                'description' => $description,
                'status' => $activate ? SceneStatus::Active : SceneStatus::Draft,
                'started_at' => $activate ? $now : null,
            ]);
        });
    }

    public function activate(Scene $scene): Scene
    {
        return DB::transaction(function () use ($scene): Scene {
            $lockedScene = Scene::query()
                ->with('gameSession')
                ->lockForUpdate()
                ->findOrFail($scene->id);

            if ($lockedScene->gameSession->status !== GameSessionStatus::Active) {
                throw new InactiveGameSessionForSceneException(
                    'Only scenes in the active game session can be activated.',
                );
            }

            Scene::query()
                ->where('game_session_id', $lockedScene->game_session_id)
                ->active()
                ->whereKeyNot($lockedScene->id)
                ->update([
                    'status' => SceneStatus::Draft,
                    'updated_at' => now(),
                ]);

            $lockedScene->update([
                'status' => SceneStatus::Active,
                'started_at' => $lockedScene->started_at ?? now(),
                'ended_at' => null,
            ]);

            $this->contexts->unfreeze($lockedScene);

            return $lockedScene->refresh();
        });
    }

    public function close(Scene $scene): Scene
    {
        $closed = DB::transaction(function () use ($scene): Scene {
            $lockedScene = Scene::query()
                ->with('gameSession')
                ->lockForUpdate()
                ->findOrFail($scene->id);

            if ($lockedScene->status !== SceneStatus::Closed) {
                if ($lockedScene->gameSession->status !== GameSessionStatus::Active) {
                    throw new InactiveGameSessionForSceneException(
                        'Only scenes in the active game session can be closed.',
                    );
                }

                $lockedScene->update([
                    'status' => SceneStatus::Closed,
                    'ended_at' => now(),
                ]);
            }

            $this->contexts->freeze($lockedScene);

            return $lockedScene->refresh();
        });

        $this->diaryScheduler->scheduleSummary($closed);

        return $closed;
    }
}
