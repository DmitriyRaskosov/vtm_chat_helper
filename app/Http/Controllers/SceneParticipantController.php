<?php

namespace App\Http\Controllers;

use App\Enums\CharacterType;
use App\Enums\SceneParticipantRole;
use App\Http\Requests\StoreSceneParticipantRequest;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Scene\SceneParticipantService;
use Illuminate\Http\JsonResponse;

class SceneParticipantController extends Controller
{
    public function __construct(private SceneParticipantService $participants) {}

    public function index(Scene $scene): JsonResponse
    {
        $participants = $this->participants->list($scene);
        $characters = $this->participants->charactersByIds($participants->pluck('character_id'));

        return response()->json([
            'participants' => $participants
                ->map(fn (SceneParticipant $participant): array => $this->serialize(
                    $participant,
                    $characters->get($participant->character_id),
                ))
                ->values(),
        ]);
    }

    public function store(StoreSceneParticipantRequest $request, Scene $scene): JsonResponse
    {
        $entered = $this->participants->enterWithCharacter(
            $scene,
            (int) $request->validated('character_id'),
            SceneParticipantRole::from((string) $request->validated('role')),
            $request->boolean('visible', true),
        );

        return response()->json([
            'participant' => $this->serialize($entered['participant'], $entered['character']),
        ], 201);
    }

    public function leave(Scene $scene, Character $character): JsonResponse
    {
        $character->loadMissing('entity');
        $participant = $this->participants->leave($scene, $character);

        return response()->json(['participant' => $this->serialize($participant, $character)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SceneParticipant $participant, ?Character $character = null): array
    {
        if ($character === null) {
            $character = $this->participants->characterForSerialization($participant);
        }

        $name = $character?->entity?->canonical_name;
        $characterType = $character?->character_type;

        return [
            'id' => (int) $participant->id,
            'scene_id' => (int) $participant->scene_id,
            'character_id' => (int) $participant->character_id,
            'character_name' => is_string($name) && $name !== '' ? $name : null,
            'character_type' => $characterType instanceof CharacterType ? $characterType->value : $characterType,
            'user_id' => $character?->user_id === null ? null : (int) $character->user_id,
            'role' => $participant->role->value,
            'visible' => (bool) $participant->visible,
            'is_current' => (bool) $participant->is_current,
            'entered_at' => $participant->entered_at?->toISOString(),
            'left_at' => $participant->left_at?->toISOString(),
        ];
    }
}
