<?php

namespace App\Scene;

use App\Character\Exceptions\CharacterNotInChronicleException;
use App\Enums\CharacterType;
use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Models\Character;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\User;
use App\Scene\Exceptions\InvalidSceneParticipantInputException;
use App\Scene\Exceptions\SceneDiceRollForbiddenException;
use Illuminate\Support\Facades\DB;

final class GameSessionService
{
    public function __construct(private SceneContextService $contexts) {}

    public function findActiveWithScenes(int $chronicleId): ?GameSession
    {
        return GameSession::query()
            ->where('chronicle_id', $chronicleId)
            ->active()
            ->with('scenes')
            ->first();
    }

    public function startNew(int $chronicleId, string $title, int $createdByUserId): GameSession
    {
        return DB::transaction(function () use ($chronicleId, $title, $createdByUserId): GameSession {
            $now = now();

            $activeSessionIds = GameSession::query()
                ->where('chronicle_id', $chronicleId)
                ->active()
                ->lockForUpdate()
                ->pluck('id');

            if ($activeSessionIds->isNotEmpty()) {
                Scene::query()
                    ->whereIn('game_session_id', $activeSessionIds)
                    ->where('status', '!=', SceneStatus::Closed)
                    ->update([
                        'status' => SceneStatus::Closed,
                        'ended_at' => $now,
                        'updated_at' => $now,
                    ]);

                $this->contexts->freezeMany(
                    Scene::query()
                        ->whereIn('game_session_id', $activeSessionIds)
                        ->pluck('id')
                        ->all(),
                );

                GameSession::query()
                    ->whereIn('id', $activeSessionIds)
                    ->update([
                        'status' => GameSessionStatus::Archived,
                        'updated_at' => $now,
                    ]);
            }

            $session = GameSession::query()->create([
                'chronicle_id' => $chronicleId,
                'title' => $title,
                'status' => GameSessionStatus::Active,
                'created_by' => $createdByUserId,
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
    }

    public function setActiveCharacter(GameSession $gameSession, ?int $characterId): GameSession
    {
        if ($characterId !== null) {
            $character = Character::query()->findOrFail($characterId);
            if ((int) $character->chronicle_id !== (int) $gameSession->chronicle_id) {
                throw new CharacterNotInChronicleException;
            }

            if ($character->character_type !== CharacterType::Player) {
                throw new InvalidSceneParticipantInputException(
                    'Ход можно передать только персонажу игрока.',
                );
            }
        }

        $gameSession->update(['active_character_id' => $characterId]);

        return $gameSession->refresh();
    }

    public function releaseActiveCharacterForPlayer(GameSession $gameSession, User $user): GameSession
    {
        $activeId = $gameSession->active_character_id;
        if ($activeId === null) {
            return $gameSession;
        }

        $character = Character::query()->findOrFail($activeId);
        if (! $character->isPlayableBy($user)) {
            throw new SceneDiceRollForbiddenException;
        }

        return $this->setActiveCharacter($gameSession, null);
    }
}
