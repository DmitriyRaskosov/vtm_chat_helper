<?php

namespace App\Http\Controllers;

use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Http\Requests\StoreGameSessionRequest;
use App\Models\GameSession;
use App\Models\Scene;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class GameSessionController extends Controller
{
    public function active(): JsonResponse
    {
        $gameSession = GameSession::query()
            ->active()
            ->with('scenes')
            ->first();

        return response()->json([
            'game_session' => $gameSession === null ? null : $this->serialize($gameSession),
        ]);
    }

    public function store(StoreGameSessionRequest $request): JsonResponse
    {
        $gameSession = DB::transaction(function () use ($request): GameSession {
            $now = now();

            $activeSessionIds = GameSession::query()
                ->active()
                ->lockForUpdate()
                ->pluck('id');

            if ($activeSessionIds->isNotEmpty()) {
                Scene::query()
                    ->whereIn('game_session_id', $activeSessionIds)
                    ->active()
                    ->update([
                        'status' => SceneStatus::Closed,
                        'ended_at' => $now,
                        'updated_at' => $now,
                    ]);

                GameSession::query()
                    ->whereIn('id', $activeSessionIds)
                    ->update([
                        'status' => GameSessionStatus::Archived,
                        'updated_at' => $now,
                    ]);
            }

            $session = GameSession::query()->create([
                'title' => $request->validated('title'),
                'status' => GameSessionStatus::Active,
                'created_by' => $request->user()->id,
                'activated_at' => $now,
            ]);

            $session->scenes()->create([
                'position' => 1,
                'title' => 'Начальная сцена',
                'status' => SceneStatus::Active,
                'started_at' => $now,
            ]);

            return $session->load('scenes');
        });

        return response()->json(['game_session' => $this->serialize($gameSession)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(GameSession $gameSession): array
    {
        return [
            'id' => $gameSession->id,
            'title' => $gameSession->title,
            'status' => $gameSession->status->value,
            'active_scene_id' => $gameSession->scenes
                ->firstWhere('status', SceneStatus::Active)?->id,
            'scenes' => $gameSession->scenes
                ->map(fn (Scene $scene): array => [
                    'id' => $scene->id,
                    'title' => $scene->title,
                    'description' => $scene->description,
                    'position' => $scene->position,
                    'status' => $scene->status->value,
                    'started_at' => $scene->started_at?->toISOString(),
                    'ended_at' => $scene->ended_at?->toISOString(),
                ])
                ->values(),
        ];
    }
}
