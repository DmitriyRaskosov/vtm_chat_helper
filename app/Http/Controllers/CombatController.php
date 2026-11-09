<?php

namespace App\Http\Controllers;

use App\Chronicle\Exceptions\ChronicleAccessDeniedException;
use App\Combat\CombatService;
use App\Combat\Exceptions\CharacterNotInSceneException;
use App\Combat\Exceptions\CombatParticipantNotFoundException;
use App\Http\Requests\AddParticipantRequest;
use App\Http\Requests\RollInitiativeRequest;
use App\Http\Requests\SetInitiativeRequest;
use App\Http\Requests\SetParticipantActiveRequest;
use App\Http\Requests\StoreCombatRequest;
use App\Models\Character;
use App\Models\CombatEncounter;
use App\Models\CombatParticipant;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CombatController extends Controller
{
    public function __construct(private CombatService $combat) {}

    public function store(StoreCombatRequest $request, Scene $scene): JsonResponse
    {
        $this->authorize('create', [CombatEncounter::class, $scene]);

        $existed = CombatEncounter::query()
            ->where('scene_id', $scene->id)
            ->active()
            ->exists();
        $encounter = $this->combat->startEncounter($scene);

        return response()->json([
            'combat' => $this->serializeFor($this->user($request), $encounter),
        ], $existed ? 200 : 201);
    }

    public function show(Request $request, Scene $scene): JsonResponse
    {
        $this->authorize('view', $scene);

        $encounter = CombatEncounter::query()
            ->where('scene_id', $scene->id)
            ->active()
            ->first();

        return response()->json([
            'combat' => $encounter === null
                ? null
                : $this->serializeFor($this->user($request), $encounter),
        ]);
    }

    public function addParticipant(AddParticipantRequest $request, CombatEncounter $combat): JsonResponse
    {
        $this->authorize('update', $combat);

        $character = Character::query()->find($request->integer('character_id'));
        if (! $character instanceof Character) {
            throw new CharacterNotInSceneException('Character is not a current participant of this scene.');
        }

        $this->combat->addParticipant($combat, $character, $request->boolean('is_hidden'));

        return response()->json([
            'combat' => $this->serializeFor($this->user($request), $combat->refresh()),
        ], 201);
    }

    public function setActive(
        SetParticipantActiveRequest $request,
        CombatEncounter $combat,
        CombatParticipant $participant,
    ): JsonResponse {
        $this->authorize('update', $combat);
        $this->assertParticipant($combat, $participant);
        $this->combat->setParticipantActive($participant, $request->boolean('is_active'));

        return $this->combatResponse($request, $combat);
    }

    public function reveal(Request $request, CombatEncounter $combat, CombatParticipant $participant): JsonResponse
    {
        $this->authorize('update', $combat);
        $this->assertParticipant($combat, $participant);
        $this->combat->revealParticipant($participant);

        return $this->combatResponse($request, $combat);
    }

    public function setInitiative(
        SetInitiativeRequest $request,
        CombatEncounter $combat,
        CombatParticipant $participant,
    ): JsonResponse {
        $this->authorize('update', $combat);
        $this->assertParticipant($combat, $participant);
        $this->combat->setInitiative($participant, $request->integer('initiative'));

        return $this->combatResponse($request, $combat);
    }

    public function rollInitiative(RollInitiativeRequest $request, CombatEncounter $combat): JsonResponse
    {
        $this->authorize('update', $combat);
        $keys = $request->validated('stat_keys');
        $this->combat->rollInitiative(
            $combat,
            is_array($keys) ? array_values($keys) : CombatService::DEFAULT_STAT_KEYS,
        );

        return $this->combatResponse($request, $combat);
    }

    public function nextTurn(Request $request, CombatEncounter $combat): JsonResponse
    {
        $this->authorize('update', $combat);
        $this->combat->nextTurn($combat);

        return $this->combatResponse($request, $combat);
    }

    public function finish(Request $request, CombatEncounter $combat): JsonResponse
    {
        $this->authorize('update', $combat);
        $finished = $this->combat->finishEncounter($combat);

        return response()->json([
            'combat' => $this->serializeFor($this->user($request), $finished),
        ]);
    }

    private function combatResponse(Request $request, CombatEncounter $combat): JsonResponse
    {
        return response()->json([
            'combat' => $this->serializeFor($this->user($request), $combat->refresh()),
        ]);
    }

    private function assertParticipant(CombatEncounter $combat, CombatParticipant $participant): void
    {
        if ($participant->combat_encounter_id !== $combat->id) {
            throw new CombatParticipantNotFoundException;
        }
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        if (! $user instanceof User) {
            throw new ChronicleAccessDeniedException;
        }

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFor(User $user, CombatEncounter $encounter): array
    {
        $encounter->loadMissing('scene.gameSession.chronicle');
        $keys = $encounter->stat_keys ?? CombatService::DEFAULT_STAT_KEYS;
        $ordered = $this->combat->sortByInitiative(
            $encounter->participants()->with('character.entity')->get(),
            $keys,
        );
        $storyteller = $user->isStorytellerIn($encounter->scene->gameSession->chronicle);

        return [
            'id' => $encounter->id,
            'scene_id' => $encounter->scene_id,
            'status' => $encounter->status->value,
            'current_round' => $encounter->current_round,
            'current_turn_index' => $encounter->current_turn_index,
            'stat_keys' => $encounter->stat_keys,
            'started_at' => $encounter->started_at->toISOString(),
            'ended_at' => $encounter->ended_at?->toISOString(),
            'participants' => $ordered->values()->map(
                function (CombatParticipant $participant, int $position) use ($storyteller): array {
                    $row = [
                        'position' => $position,
                        'initiative' => $participant->initiative,
                        'is_active' => $participant->is_active,
                        'is_hidden' => $participant->is_hidden,
                    ];

                    if ($storyteller || ! $participant->is_hidden) {
                        $row['id'] = $participant->id;
                        $row['character_id'] = $participant->character_id;
                        $row['name'] = $participant->character?->entity?->canonical_name;
                        $row['avatar_path'] = $participant->character?->avatar_path;
                    }

                    return $row;
                },
            )->all(),
        ];
    }
}
