<?php

namespace App\Http\Controllers;

use App\Enums\SceneParticipantRole;
use App\Http\Requests\StoreSceneParticipantRequest;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\WorldEntity;
use App\Scene\SceneFrozenException;
use App\Scene\SceneParticipantService;
use App\World\MixedChronicleException;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class SceneParticipantController extends Controller
{
    public function __construct(private SceneParticipantService $participants) {}

    public function index(Scene $scene): JsonResponse
    {
        return response()->json([
            'participants' => $this->participants
                ->list($scene)
                ->map(fn (SceneParticipant $participant): array => $this->serialize($participant))
                ->values(),
        ]);
    }

    public function store(StoreSceneParticipantRequest $request, Scene $scene): JsonResponse
    {
        $character = Character::query()->findOrFail((int) $request->validated('character_id'));
        $role = SceneParticipantRole::from((string) $request->validated('role'));
        $visible = $request->boolean('visible', true);

        try {
            $participant = $this->participants->enter($scene, $character, $role, $visible);
        } catch (SceneFrozenException|MixedChronicleException $e) {
            abort(409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['participant' => $this->serialize($participant)], 201);
    }

    public function leave(Scene $scene, Character $character): JsonResponse
    {
        try {
            $participant = $this->participants->leave($scene, $character);
        } catch (SceneFrozenException|MixedChronicleException $e) {
            abort(409, $e->getMessage());
        } catch (InvalidArgumentException $e) {
            abort(422, $e->getMessage());
        }

        return response()->json(['participant' => $this->serialize($participant)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(SceneParticipant $participant): array
    {
        $name = WorldEntity::query()->whereKey($participant->character_id)->value('canonical_name');
        $characterType = Character::query()
            ->whereKey($participant->character_id)
            ->value('character_type');

        return [
            'id' => (int) $participant->id,
            'scene_id' => (int) $participant->scene_id,
            'character_id' => (int) $participant->character_id,
            'character_name' => is_string($name) && $name !== '' ? $name : null,
            'character_type' => $characterType,
            'role' => $participant->role->value,
            'visible' => (bool) $participant->visible,
            'is_current' => (bool) $participant->is_current,
            'entered_at' => $participant->entered_at?->toISOString(),
            'left_at' => $participant->left_at?->toISOString(),
        ];
    }
}
