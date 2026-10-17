<?php

namespace App\Http\Controllers;

use App\Enums\SceneParticipantRole;
use App\Http\Requests\StoreSceneParticipantRequest;
use App\Http\Resources\SceneParticipantResource;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Scene\SceneParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SceneParticipantController extends Controller
{
    public function __construct(private SceneParticipantService $participants) {}

    public function index(Request $request, Scene $scene): JsonResponse
    {
        $participants = $this->participants->list($scene);
        $characters = $this->participants->charactersByIds($participants->pluck('character_id'));

        return response()->json([
            'participants' => $participants
                ->map(fn (SceneParticipant $participant) => (new SceneParticipantResource(
                    $participant,
                    $characters->get($participant->character_id),
                    $this->participants,
                ))->toArray($request))
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
            'participant' => (new SceneParticipantResource(
                $entered['participant'],
                $entered['character'],
                $this->participants,
            ))->toArray($request),
        ], 201);
    }

    public function leave(Request $request, Scene $scene, Character $character): JsonResponse
    {
        $character->loadMissing('entity');
        $participant = $this->participants->leave($scene, $character);

        return response()->json([
            'participant' => (new SceneParticipantResource($participant, $character, $this->participants))
                ->toArray($request),
        ]);
    }
}
