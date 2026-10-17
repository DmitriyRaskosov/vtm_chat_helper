<?php

namespace App\Http\Controllers;

use App\Context\DiaryPreviewService;
use App\Diary\DiaryEntryEditor;
use App\Http\Requests\PreviewDiaryRequest;
use App\Http\Requests\UpdateDiaryEntryRequest;
use App\Http\Resources\DiaryEntryListResource;
use App\Http\Resources\DiaryEntryResource;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DiaryEntryController extends Controller
{
    public function __construct(
        private DiaryPreviewService $previewer,
        private DiaryEntryEditor $editor,
    ) {}

    public function index(Request $request, Character $character): JsonResponse
    {
        $entries = CharacterDiaryEntry::query()
            ->with('scene:id,title,game_session_id')
            ->where('character_id', $character->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'character_id' => (int) $character->id,
            'entries' => $entries
                ->map(fn (CharacterDiaryEntry $e): array => (new DiaryEntryListResource($e))->toArray($request))
                ->values(),
        ]);
    }

    public function show(Request $request, CharacterDiaryEntry $diaryEntry): JsonResponse
    {
        $diaryEntry->loadMissing('scene:id,title,game_session_id');

        return response()->json([
            'entry' => (new DiaryEntryResource($diaryEntry))->toArray($request),
        ]);
    }

    public function preview(
        PreviewDiaryRequest $request,
        Character $character,
    ): JsonResponse {
        $sceneId = $request->has('scene_id') ? (int) $request->validated('scene_id') : null;
        $prompt = (string) ($request->validated('prompt') ?? '');

        return response()->json($this->previewer->preview($character, $sceneId, $prompt));
    }

    public function update(
        UpdateDiaryEntryRequest $request,
        CharacterDiaryEntry $diaryEntry,
    ): JsonResponse {
        $entry = trim((string) $request->validated('entry'));
        $diaryEntry = $this->editor->update($diaryEntry, $entry);

        return response()->json([
            'entry' => (new DiaryEntryResource($diaryEntry))->toArray($request),
        ]);
    }

    public function destroy(CharacterDiaryEntry $diaryEntry): Response
    {
        $this->editor->destroy($diaryEntry);

        return response()->noContent();
    }

    public function regenerate(Request $request, CharacterDiaryEntry $diaryEntry): JsonResponse
    {
        $diaryEntry = $this->editor->regenerate($diaryEntry);

        return response()->json([
            'entry' => (new DiaryEntryResource($diaryEntry))->toArray($request),
        ]);
    }
}
