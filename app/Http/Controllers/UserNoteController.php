<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateUserNoteRequest;
use App\Models\UserNote;
use App\Notes\UserNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNoteController extends Controller
{
    public function __construct(private UserNoteService $notes) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scene_id' => ['sometimes', 'nullable', 'integer', 'exists:scenes,id'],
        ]);

        $user = $request->user();
        $sceneId = array_key_exists('scene_id', $validated) && $validated['scene_id'] !== null
            ? (int) $validated['scene_id']
            : null;

        $note = $this->notes->findForUser($user, $sceneId);

        if ($note !== null) {
            $this->authorize('view', $note);
        }

        return response()->json([
            'note' => $note === null ? null : $this->serialize($note),
        ]);
    }

    public function update(UpdateUserNoteRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $user = $request->user();
        $sceneId = array_key_exists('scene_id', $validated) && $validated['scene_id'] !== null
            ? (int) $validated['scene_id']
            : null;

        $existing = $this->notes->findForUser($user, $sceneId);
        if ($existing !== null) {
            $this->authorize('update', $existing);
        }

        $note = $this->notes->upsert($user, $sceneId, $validated['content']);

        return response()->json([
            'note' => $this->serialize($note),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(UserNote $note): array
    {
        return [
            'id' => (int) $note->id,
            'scene_id' => $note->scene_id === null ? null : (int) $note->scene_id,
            'content' => $note->content,
            'updated_at' => $note->updated_at?->toIso8601String(),
        ];
    }
}
