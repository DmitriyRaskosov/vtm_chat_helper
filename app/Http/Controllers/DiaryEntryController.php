<?php

namespace App\Http\Controllers;

use App\Context\DiaryPreviewService;
use App\Diary\DiaryEntryEditor;
use App\Enums\DiaryEmotion;
use App\Http\Requests\PreviewDiaryRequest;
use App\Http\Requests\UpdateDiaryEntryRequest;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Models\Scene;
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
                ->map(fn (CharacterDiaryEntry $e): array => $this->serializeList($e))
                ->values(),
        ]);
    }

    public function show(CharacterDiaryEntry $diaryEntry): JsonResponse
    {
        $diaryEntry->loadMissing('scene:id,title,game_session_id');

        return response()->json([
            'entry' => $this->serializeFull($diaryEntry),
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
            'entry' => $this->serializeFull($diaryEntry),
        ]);
    }

    public function destroy(CharacterDiaryEntry $diaryEntry): Response
    {
        $this->editor->destroy($diaryEntry);

        return response()->noContent();
    }

    public function regenerate(CharacterDiaryEntry $diaryEntry): JsonResponse
    {
        $diaryEntry = $this->editor->regenerate($diaryEntry);

        return response()->json([
            'entry' => $this->serializeFull($diaryEntry),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeList(CharacterDiaryEntry $entry): array
    {
        $text = trim((string) $entry->entry);
        $emotion = $entry->getAttribute('emotion');

        return [
            'id' => (int) $entry->id,
            'character_id' => (int) $entry->character_id,
            'scene_id' => $entry->scene_id === null ? null : (int) $entry->scene_id,
            'scene_title' => $entry->scene instanceof Scene ? $entry->scene->title : null,
            'level' => (int) $entry->level,
            'from_message_id' => $entry->from_message_id === null ? null : (int) $entry->from_message_id,
            'to_message_id' => $entry->to_message_id === null ? null : (int) $entry->to_message_id,
            'is_stale' => (bool) $entry->is_stale,
            'importance' => $entry->importance === null ? null : (float) $entry->importance,
            'emotion' => $emotion instanceof DiaryEmotion ? $emotion->value : null,
            'preview' => mb_substr($text, 0, 300),
            'word_count' => count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []),
            'created_at' => $entry->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFull(CharacterDiaryEntry $entry): array
    {
        return [
            ...$this->serializeList($entry),
            'entry' => $entry->entry,
        ];
    }
}
