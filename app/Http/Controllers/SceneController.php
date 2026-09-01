<?php

namespace App\Http\Controllers;

use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Http\Requests\StoreSceneRequest;
use App\Models\GameSession;
use App\Models\Scene;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SceneController extends Controller
{
    public function store(StoreSceneRequest $request, GameSession $gameSession): JsonResponse
    {
        $scene = DB::transaction(function () use ($request, $gameSession): Scene {
            $session = GameSession::query()->lockForUpdate()->findOrFail($gameSession->id);

            abort_unless(
                $session->status === GameSessionStatus::Active,
                409,
                'Scenes can only be added to the active game session.',
            );

            $activate = $request->boolean('activate', true);
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
                'title' => $request->validated('title'),
                'description' => $request->validated('description'),
                'status' => $activate ? SceneStatus::Active : SceneStatus::Draft,
                'started_at' => $activate ? $now : null,
            ]);
        });

        return response()->json(['scene' => $this->serialize($scene)], 201);
    }

    public function activate(Scene $scene): JsonResponse
    {
        $scene = DB::transaction(function () use ($scene): Scene {
            $lockedScene = Scene::query()
                ->with('gameSession')
                ->lockForUpdate()
                ->findOrFail($scene->id);

            abort_unless(
                $lockedScene->gameSession->status === GameSessionStatus::Active,
                409,
                'Only scenes in the active game session can be activated.',
            );
            abort_if(
                $lockedScene->status === SceneStatus::Closed,
                409,
                'A closed scene cannot be activated.',
            );

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

            return $lockedScene->refresh();
        });

        return response()->json(['scene' => $this->serialize($scene)]);
    }

    public function close(Scene $scene): JsonResponse
    {
        if ($scene->status !== SceneStatus::Closed) {
            abort_unless(
                $scene->gameSession->status === GameSessionStatus::Active,
                409,
                'Only scenes in the active game session can be closed.',
            );

            $scene->update([
                'status' => SceneStatus::Closed,
                'ended_at' => now(),
            ]);
        }

        return response()->json(['scene' => $this->serialize($scene->refresh())]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Scene $scene): array
    {
        return [
            'id' => $scene->id,
            'game_session_id' => $scene->game_session_id,
            'title' => $scene->title,
            'description' => $scene->description,
            'position' => $scene->position,
            'status' => $scene->status->value,
            'started_at' => $scene->started_at?->toISOString(),
            'ended_at' => $scene->ended_at?->toISOString(),
        ];
    }
}
