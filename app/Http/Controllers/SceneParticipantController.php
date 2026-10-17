<?php

namespace App\Http\Controllers;

use App\Enums\SceneParticipantRole;
use App\Http\Requests\StoreSceneParticipantRequest;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Scene\SceneParticipantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class SceneParticipantController extends Controller
{
    public function __construct(private SceneParticipantService $participants) {}

    public function index(Scene $scene): JsonResponse
    {
        $participants = $this->participants->list($scene);
        $characters = $this->charactersWithEntity($participants->pluck('character_id'));

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
        $character = Character::query()
            ->with('entity')
            ->findOrFail((int) $request->validated('character_id'));
        $role = SceneParticipantRole::from((string) $request->validated('role'));
        $visible = $request->boolean('visible', true);

        $participant = $this->participants->enter($scene, $character, $role, $visible);

        return response()->json(['participant' => $this->serialize($participant, $character)], 201);
    }

    public function leave(Scene $scene, Character $character): JsonResponse
    {
        $character->loadMissing('entity');
        $participant = $this->participants->leave($scene, $character);

        return response()->json(['participant' => $this->serialize($participant, $character)]);
    }

    /**
     * @param  Collection<int, int|string|null>  $characterIds
     * @return Collection<int, Character>
     */
    private function charactersWithEntity(Collection $characterIds): Collection
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

    /**
     * @return array<string, mixed>
     */
    private function serialize(SceneParticipant $participant, ?Character $character = null): array
    {
        if ($character === null) {
            $character = Character::query()
                ->with('entity')
                ->whereKey($participant->character_id)
                ->first(['id', 'character_type', 'user_id']);
        }

        $name = $character?->entity?->canonical_name;

        return [
            'id' => (int) $participant->id,
            'scene_id' => (int) $participant->scene_id,
            'character_id' => (int) $participant->character_id,
            'character_name' => is_string($name) && $name !== '' ? $name : null,
            'character_type' => $character?->character_type,
            'user_id' => $character?->user_id === null ? null : (int) $character->user_id,
            'role' => $participant->role->value,
            'visible' => (bool) $participant->visible,
            'is_current' => (bool) $participant->is_current,
            'entered_at' => $participant->entered_at?->toISOString(),
            'left_at' => $participant->left_at?->toISOString(),
        ];
    }
}
