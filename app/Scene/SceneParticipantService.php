<?php

namespace App\Scene;

use App\Diary\DiaryScheduler;
use App\Enums\SceneParticipantRole;
use App\Exceptions\ConflictException;
use App\Exceptions\InvalidInputException;
use App\Models\Character;
use App\Models\Message;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Scene\Exceptions\InvalidSceneParticipantInputException;
use App\World\MixedChronicleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SceneParticipantService
{
    public function __construct(
        private SceneContextService $contexts,
        private DiaryScheduler $diaryScheduler,
    ) {}

    /**
     * @return array{participant: SceneParticipant, character: Character}
     */
    public function enterWithCharacter(
        Scene $scene,
        int $characterId,
        SceneParticipantRole $role,
        bool $visible = true,
    ): array {
        $character = Character::query()
            ->with('entity')
            ->findOrFail($characterId);

        $participant = $this->enter($scene, $character, $role, $visible);

        return [
            'participant' => $participant,
            'character' => $character,
        ];
    }

    /**
     * @param  Collection<int, int|string|null>  $characterIds
     * @return Collection<int, Character>
     */
    public function charactersByIds(Collection $characterIds): Collection
    {
        $ids = $characterIds->filter()->unique()->values()->all();
        if ($ids === []) {
            return collect();
        }

        return Character::query()
            ->whereIn('id', $ids)
            ->with('entity')
            ->get(['id', 'character_type', 'user_id'])
            ->keyBy('id');
    }

    public function characterForSerialization(SceneParticipant $participant): ?Character
    {
        return Character::query()
            ->with('entity')
            ->whereKey($participant->character_id)
            ->first(['id', 'character_type', 'user_id']);
    }

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
            throw new InvalidSceneParticipantInputException('An archived character cannot enter a scene.');
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

            $lastMessageId = Message::query()
                ->where('scene_id', $lockedScene->id)
                ->max('id');

            $enteredMessageId = $lastMessageId === null ? null : (int) $lastMessageId;

            return SceneParticipant::query()->create([
                'scene_id' => $lockedScene->id,
                'chronicle_id' => $character->chronicle_id,
                'character_id' => $character->id,
                'role' => $role,
                'visible' => $visible,
                'is_current' => true,
                'entered_at' => now(),
                'entered_message_id' => $enteredMessageId,
                'last_diary_message_id' => $enteredMessageId,  // всё до входа не считаем
            ])->refresh();
        });
    }

    public function leave(Scene $scene, Character $character): SceneParticipant
    {
        $participant = DB::transaction(function () use ($scene, $character): SceneParticipant {
            $lockedScene = Scene::query()->lockForUpdate()->findOrFail($scene->id);
            $this->contexts->assertMutable($lockedScene);

            $current = SceneParticipant::query()
                ->where('scene_id', $lockedScene->id)
                ->where('character_id', $character->id)
                ->where('is_current', true)
                ->lockForUpdate()
                ->first();

            if ($current === null) {
                throw new InvalidSceneParticipantInputException('Character is not a current participant of this scene.');
            }

            $lastMessageId = Message::query()
                ->where('scene_id', $lockedScene->id)
                ->max('id');

            $current->is_current = false;
            $current->left_at = now();
            $current->left_message_id = $lastMessageId === null ? null : (int) $lastMessageId;
            $current->save();

            return $current->refresh();
        });

        // Финальный L0 для ушедшего NPC — вне транзакции
        if ($participant->left_message_id !== null) {
            $this->diaryScheduler->scheduleFinal($scene, $character);
        }

        return $participant;
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
            } catch (ConflictException|InvalidInputException) {
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
}
