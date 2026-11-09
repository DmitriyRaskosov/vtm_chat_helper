<?php

namespace App\Combat;

use App\Broadcasting\SafeBroadcaster;
use App\Character\CharacterStatsService;
use App\Combat\Exceptions\CharacterNotInSceneException;
use App\Combat\Exceptions\DuplicateCombatParticipantException;
use App\Combat\Exceptions\NoActiveCombatParticipantException;
use App\Diary\DiaryScheduler;
use App\Enums\CharacterType;
use App\Enums\CombatEncounterStatus;
use App\Events\CombatParticipantUpdatedEvent;
use App\Events\CombatStartedEvent;
use App\Events\CombatTurnChangedEvent;
use App\Jobs\WriteDiaryJob;
use App\Models\Character;
use App\Models\CombatEncounter;
use App\Models\CombatParticipant;
use App\Models\GameSession;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Scene\GameSessionService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CombatService
{
    /**
     * @var list<string>
     */
    public const DEFAULT_STAT_KEYS = ['dexterity', 'wits'];

    public function __construct(
        private GameSessionService $turns,
        private DiaryScheduler $diaryScheduler,
    ) {}

    public function startEncounter(Scene $scene): CombatEncounter
    {
        $existing = $this->findActive($scene);
        if ($existing !== null) {
            return $existing;
        }

        try {
            $created = DB::transaction(function () use ($scene): CombatEncounter {
                return CombatEncounter::query()->create([
                    'scene_id' => $scene->id,
                    'status' => CombatEncounterStatus::Active,
                    'current_round' => 1,
                    'current_turn_index' => 0,
                    'stat_keys' => null,
                    'started_at' => now(),
                    'ended_at' => null,
                ]);
            });
        } catch (UniqueConstraintViolationException $e) {
            $won = $this->findActive($scene);
            if ($won === null) {
                throw $e;
            }

            return $won;
        }

        $this->broadcastStarted($created);
        $this->diaryScheduler->scheduleSummary($scene);

        return $created;
    }

    public function addParticipant(
        CombatEncounter $encounter,
        Character $character,
        bool $isHidden = false,
    ): CombatParticipant {
        $inScene = SceneParticipant::query()
            ->where('scene_id', $encounter->scene_id)
            ->where('character_id', $character->id)
            ->where('is_current', true)
            ->exists();

        if (! $inScene) {
            throw new CharacterNotInSceneException('Character is not a current participant of this scene.');
        }

        $alreadyAdded = CombatParticipant::query()
            ->where('combat_encounter_id', $encounter->id)
            ->where('character_id', $character->id)
            ->exists();

        if ($alreadyAdded) {
            throw new DuplicateCombatParticipantException('Character is already in this combat encounter.');
        }

        try {
            $created = DB::transaction(fn (): CombatParticipant => CombatParticipant::query()->create([
                'combat_encounter_id' => $encounter->id,
                'character_id' => $character->id,
                'initiative' => null,
                'is_active' => true,
                'is_hidden' => $isHidden,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateCombatParticipantException('Character is already in this combat encounter.');
        }

        $this->broadcastParticipantChange($created);

        return $created;
    }

    /**
     * @param  list<string>  $statKeys
     */
    public function rollInitiative(
        CombatEncounter $encounter,
        array $statKeys = self::DEFAULT_STAT_KEYS,
    ): CombatEncounter {
        $updated = DB::transaction(function () use ($encounter, $statKeys): CombatEncounter {
            $locked = CombatEncounter::query()->lockForUpdate()->findOrFail($encounter->id);
            $locked->load('participants.character');

            foreach ($locked->participants as $participant) {
                $participant->initiative = $participant->character === null
                    ? 0
                    : $this->initiativeFor($participant->character, $statKeys);
                $participant->save();
            }

            $locked->stat_keys = $statKeys;
            $locked->current_turn_index = 0;
            $locked->current_round = 1;
            $locked->save();

            return $locked->refresh();
        });

        $this->broadcastParticipant($updated, null);

        return $updated;
    }

    public function nextTurn(CombatEncounter $encounter): CombatEncounter
    {
        $updated = DB::transaction(function () use ($encounter): CombatEncounter {
            $locked = CombatEncounter::query()->lockForUpdate()->findOrFail($encounter->id);
            $ordered = $this->orderedParticipants($locked);
            $activeIndexes = [];
            foreach ($ordered as $index => $participant) {
                if ($participant->is_active) {
                    $activeIndexes[] = $index;
                }
            }

            if ($activeIndexes === []) {
                throw new NoActiveCombatParticipantException('The combat encounter has no active participants.');
            }

            $next = null;
            foreach ($activeIndexes as $index) {
                if ($index > $locked->current_turn_index) {
                    $next = $index;
                    break;
                }
            }

            if ($next === null) {
                $next = $activeIndexes[0];
                $locked->current_round++;
            }

            $locked->current_turn_index = $next;
            $locked->save();
            $locked->refresh();

            if ($locked->status === CombatEncounterStatus::Active) {
                $this->syncActiveCharacter($locked, $this->currentCharacterId($locked));
            }

            return $locked->refresh();
        });

        $this->broadcastTurn($updated);

        return $updated;
    }

    public function setParticipantActive(CombatParticipant $participant, bool $isActive): CombatParticipant
    {
        $updated = DB::transaction(function () use ($participant, $isActive): CombatParticipant {
            $encounter = CombatEncounter::query()->lockForUpdate()->findOrFail($participant->combat_encounter_id);
            $currentCharacterId = $this->currentCharacterId($encounter);

            $participant->is_active = $isActive;
            $participant->save();

            if ($currentCharacterId !== null) {
                $this->reindexCurrentTurn($encounter, $currentCharacterId);
            }

            return $participant->refresh();
        });

        $this->broadcastParticipantChange($updated);

        return $updated;
    }

    public function revealParticipant(CombatParticipant $participant): CombatParticipant
    {
        $participant->is_hidden = false;
        $participant->save();
        $revealed = $participant->refresh();
        $this->broadcastParticipantChange($revealed);

        return $revealed;
    }

    public function setInitiative(CombatParticipant $participant, int $initiative): CombatParticipant
    {
        $updated = DB::transaction(function () use ($participant, $initiative): CombatParticipant {
            $encounter = CombatEncounter::query()->lockForUpdate()->findOrFail($participant->combat_encounter_id);
            $currentCharacterId = $this->currentCharacterId($encounter);

            $participant->initiative = $initiative;
            $participant->save();

            if ($currentCharacterId !== null) {
                $this->reindexCurrentTurn($encounter, $currentCharacterId);
            }

            return $participant->refresh();
        });

        $this->broadcastParticipantChange($updated);

        return $updated;
    }

    public function finishEncounter(CombatEncounter $encounter): CombatEncounter
    {
        if ($encounter->status === CombatEncounterStatus::Finished) {
            return $encounter;
        }

        $scene = Scene::query()->findOrFail($encounter->scene_id);
        WriteDiaryJob::dispatchSync($scene->id);

        $encounter->status = CombatEncounterStatus::Finished;
        $encounter->ended_at = now();
        $encounter->save();
        $this->syncActiveCharacter($encounter, null);
        $finished = $encounter->refresh();
        $this->broadcastTurn($finished);
        $this->diaryScheduler->scheduleSummary($scene, (int) $encounter->id);

        return $finished;
    }

    public function activeEncounterId(Scene $scene): ?int
    {
        $active = $this->findActive($scene);

        return $active === null ? null : (int) $active->id;
    }

    public function finishForScene(Scene $scene): void
    {
        $active = $this->findActive($scene);
        if ($active === null) {
            return;
        }

        $this->finishEncounter($active);
    }

    public function deactivateForParticipant(SceneParticipant $participant): void
    {
        $encounter = CombatEncounter::query()
            ->where('scene_id', $participant->scene_id)
            ->where('status', CombatEncounterStatus::Active)
            ->first();

        if ($encounter === null) {
            return;
        }

        $combatant = CombatParticipant::query()
            ->where('combat_encounter_id', $encounter->id)
            ->where('character_id', $participant->character_id)
            ->first();

        if ($combatant === null) {
            return;
        }

        $this->setParticipantActive($combatant, false);
    }

    /**
     * @param  Collection<int, CombatParticipant>  $participants
     * @param  list<string>  $statKeys
     * @return Collection<int, CombatParticipant>
     */
    public function sortByInitiative(Collection $participants, array $statKeys): Collection
    {
        if ($participants->every(fn (CombatParticipant $participant): bool => $participant->initiative === null)) {
            return $participants->sortBy('id')->values();
        }

        $firstKey = $statKeys[0] ?? null;

        return $participants
            ->sort(function (CombatParticipant $left, CombatParticipant $right) use ($firstKey): int {
                $byActive = ((int) $right->is_active) <=> ((int) $left->is_active);
                if ($byActive !== 0) {
                    return $byActive;
                }

                $leftMissing = $left->initiative === null;
                $rightMissing = $right->initiative === null;
                if ($leftMissing !== $rightMissing) {
                    return $leftMissing <=> $rightMissing;
                }

                if (! $leftMissing && $left->initiative !== $right->initiative) {
                    return $right->initiative <=> $left->initiative;
                }

                $byStat = $this->firstStat($right, $firstKey) <=> $this->firstStat($left, $firstKey);
                if ($byStat !== 0) {
                    return $byStat;
                }

                return $left->id <=> $right->id;
            })
            ->values();
    }

    /**
     * @param  list<string>  $statKeys
     */
    private function initiativeFor(Character $character, array $statKeys): int
    {
        $stats = is_array($character->stats) ? $character->stats : [];
        $sum = 0;
        foreach ($statKeys as $key) {
            $sum += CharacterStatsService::filledDots($stats[$key] ?? null, 0);
        }

        return $sum + CharacterStatsService::legacyState($stats)['health_penalty'];
    }

    private function firstStat(CombatParticipant $participant, ?string $key): int
    {
        if ($key === null || ! $participant->relationLoaded('character')) {
            return 0;
        }

        $stats = $participant->character?->stats;

        return CharacterStatsService::filledDots(is_array($stats) ? ($stats[$key] ?? null) : null, 0);
    }

    /**
     * @return Collection<int, CombatParticipant>
     */
    private function orderedParticipants(CombatEncounter $encounter): Collection
    {
        $participants = $encounter->participants()->with('character')->get();

        return $this->sortByInitiative(
            $participants,
            $encounter->stat_keys ?? self::DEFAULT_STAT_KEYS,
        );
    }

    private function currentCharacterId(CombatEncounter $encounter): ?int
    {
        $current = $this->orderedParticipants($encounter)->get($encounter->current_turn_index);

        return $current === null ? null : (int) $current->character_id;
    }

    private function reindexCurrentTurn(CombatEncounter $encounter, int $characterId): void
    {
        $index = $this->orderedParticipants($encounter)->search(
            fn (CombatParticipant $participant): bool => (int) $participant->character_id === $characterId,
        );

        $encounter->current_turn_index = $index === false ? 0 : (int) $index;
        $encounter->save();
    }

    private function broadcastStarted(CombatEncounter $encounter): void
    {
        $context = $this->broadcastContext($encounter);
        SafeBroadcaster::send(new CombatStartedEvent(
            $context['chronicle_id'],
            $context['scene_id'],
            (int) $encounter->id,
        ));
    }

    private function broadcastTurn(CombatEncounter $encounter): void
    {
        $context = $this->broadcastContext($encounter);
        $encounter->loadMissing('scene.gameSession');
        $activeId = $encounter->scene->gameSession->active_character_id;

        SafeBroadcaster::send(new CombatTurnChangedEvent(
            $context['chronicle_id'],
            $context['scene_id'],
            (int) $encounter->id,
            (int) $encounter->current_round,
            (int) $encounter->current_turn_index,
            $activeId === null ? null : (int) $activeId,
            $encounter->status->value,
        ));
    }

    private function broadcastParticipant(CombatEncounter $encounter, ?int $participantId): void
    {
        $context = $this->broadcastContext($encounter);
        SafeBroadcaster::send(new CombatParticipantUpdatedEvent(
            $context['chronicle_id'],
            $context['scene_id'],
            (int) $encounter->id,
            $participantId,
        ));
    }

    private function broadcastParticipantChange(CombatParticipant $participant): void
    {
        $participant->load('encounter');
        $encounter = $participant->encounter;
        if (! $encounter instanceof CombatEncounter) {
            return;
        }

        $this->broadcastParticipant($encounter, (int) $participant->id);
    }

    /**
     * @return array{chronicle_id: int, scene_id: int}
     */
    private function broadcastContext(CombatEncounter $encounter): array
    {
        $encounter->loadMissing('scene.gameSession');

        return [
            'chronicle_id' => (int) $encounter->scene->gameSession->chronicle_id,
            'scene_id' => (int) $encounter->scene_id,
        ];
    }

    private function syncActiveCharacter(CombatEncounter $encounter, ?int $characterId): void
    {
        $encounter->loadMissing('scene.gameSession');
        $session = $encounter->scene?->gameSession;
        if (! $session instanceof GameSession) {
            return;
        }

        $this->turns->setActiveCharacter($session, $this->playerTurnId($session, $characterId));
    }

    private function playerTurnId(GameSession $session, ?int $characterId): ?int
    {
        if ($characterId === null) {
            return null;
        }

        $character = Character::query()->find($characterId);
        if (
            ! $character instanceof Character
            || $character->character_type !== CharacterType::Player
            || (int) $character->chronicle_id !== (int) $session->chronicle_id
        ) {
            return null;
        }

        return (int) $character->id;
    }

    private function findActive(Scene $scene): ?CombatEncounter
    {
        return CombatEncounter::query()
            ->where('scene_id', $scene->id)
            ->where('status', CombatEncounterStatus::Active)
            ->first();
    }
}
