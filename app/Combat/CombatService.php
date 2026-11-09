<?php

namespace App\Combat;

use App\Character\CharacterStatsService;
use App\Combat\Exceptions\CharacterNotInSceneException;
use App\Combat\Exceptions\DuplicateCombatParticipantException;
use App\Combat\Exceptions\NoActiveCombatParticipantException;
use App\Enums\CombatEncounterStatus;
use App\Models\Character;
use App\Models\CombatEncounter;
use App\Models\CombatParticipant;
use App\Models\Scene;
use App\Models\SceneParticipant;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class CombatService
{
    /**
     * @var list<string>
     */
    public const DEFAULT_STAT_KEYS = ['dexterity', 'wits'];

    public function startEncounter(Scene $scene): CombatEncounter
    {
        $existing = $this->findActive($scene);
        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($scene): CombatEncounter {
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
            return DB::transaction(fn (): CombatParticipant => CombatParticipant::query()->create([
                'combat_encounter_id' => $encounter->id,
                'character_id' => $character->id,
                'initiative' => null,
                'is_active' => true,
                'is_hidden' => $isHidden,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw new DuplicateCombatParticipantException('Character is already in this combat encounter.');
        }
    }

    /**
     * @param  list<string>  $statKeys
     */
    public function rollInitiative(
        CombatEncounter $encounter,
        array $statKeys = self::DEFAULT_STAT_KEYS,
    ): CombatEncounter {
        return DB::transaction(function () use ($encounter, $statKeys): CombatEncounter {
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
    }

    public function nextTurn(CombatEncounter $encounter): CombatEncounter
    {
        return DB::transaction(function () use ($encounter): CombatEncounter {
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

            return $locked->refresh();
        });
    }

    public function setParticipantActive(CombatParticipant $participant, bool $isActive): CombatParticipant
    {
        return DB::transaction(function () use ($participant, $isActive): CombatParticipant {
            $encounter = CombatEncounter::query()->lockForUpdate()->findOrFail($participant->combat_encounter_id);
            $currentCharacterId = $this->currentCharacterId($encounter);

            $participant->is_active = $isActive;
            $participant->save();

            if ($currentCharacterId !== null) {
                $this->reindexCurrentTurn($encounter, $currentCharacterId);
            }

            return $participant->refresh();
        });
    }

    public function revealParticipant(CombatParticipant $participant): CombatParticipant
    {
        $participant->is_hidden = false;
        $participant->save();

        return $participant->refresh();
    }

    public function setInitiative(CombatParticipant $participant, int $initiative): CombatParticipant
    {
        return DB::transaction(function () use ($participant, $initiative): CombatParticipant {
            $encounter = CombatEncounter::query()->lockForUpdate()->findOrFail($participant->combat_encounter_id);
            $currentCharacterId = $this->currentCharacterId($encounter);

            $participant->initiative = $initiative;
            $participant->save();

            if ($currentCharacterId !== null) {
                $this->reindexCurrentTurn($encounter, $currentCharacterId);
            }

            return $participant->refresh();
        });
    }

    public function finishEncounter(CombatEncounter $encounter): CombatEncounter
    {
        if ($encounter->status === CombatEncounterStatus::Finished) {
            return $encounter;
        }

        $encounter->status = CombatEncounterStatus::Finished;
        $encounter->ended_at = now();
        $encounter->save();

        return $encounter->refresh();
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

    private function findActive(Scene $scene): ?CombatEncounter
    {
        return CombatEncounter::query()
            ->where('scene_id', $scene->id)
            ->where('status', CombatEncounterStatus::Active)
            ->first();
    }
}
