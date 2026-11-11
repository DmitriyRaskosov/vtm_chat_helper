<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveUserNoteRequest;
use App\Http\Resources\UserNoteResource;
use App\Models\Character;
use App\Models\Chronicle;
use App\Notes\UserNoteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserNoteController extends Controller
{
    public function __construct(private UserNoteService $notes) {}

    public function show(Request $request, Chronicle $chronicle, Character $character): JsonResponse
    {
        $this->authorize('manageNotes', $character);

        $note = $this->notes->getNote($request->user(), $character, $chronicle);

        return response()->json([
            'note' => $note === null ? null : (new UserNoteResource($note))->toArray($request),
        ]);
    }

    public function update(SaveUserNoteRequest $request, Chronicle $chronicle, Character $character): JsonResponse
    {
        $this->authorize('manageNotes', $character);

        $note = $this->notes->saveNote(
            $request->user(),
            $character,
            $chronicle,
            $request->validated('body'),
        );

        return response()->json([
            'note' => (new UserNoteResource($note))->toArray($request),
        ]);
    }
}
