<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDiaryEntryRequest;
use App\Models\Character;
use App\Models\CharacterDiaryEntry;
use App\Diary\DiarySummarizerService;
use App\Diary\DiaryWriterService;
use App\Rag\EmbeddingProvider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class DiaryEntryController extends Controller
{
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

    public function update(
        UpdateDiaryEntryRequest $request,
        CharacterDiaryEntry $diaryEntry,
    ): JsonResponse {
        $entry = trim((string) $request->validated('entry'));
        if ($entry === '') {
            abort(422, 'Entry cannot be empty.');
        }

        DB::transaction(function () use ($diaryEntry, $entry): void {
            $diaryEntry->update(['entry' => $entry]);

            // Пересчитать embedding — иначе retrieval сломается
            $vector = app(EmbeddingProvider::class)->embed($entry);

            DB::statement(
                'UPDATE character_diary_entries SET embedding = ?::vector WHERE id = ?',
                [$this->formatVector($vector), $diaryEntry->id],
            );
        });

        $diaryEntry->refresh()->loadMissing('scene:id,title,game_session_id');

        return response()->json([
            'entry' => $this->serializeFull($diaryEntry),
        ]);
    }

    public function destroy(CharacterDiaryEntry $diaryEntry): Response
    {
        $diaryEntry->delete();

        return response()->noContent();
    }

    public function regenerate(
    CharacterDiaryEntry $diaryEntry,
    DiaryWriterService $writer,
    DiarySummarizerService $summarizer,
    ): JsonResponse {
        $level = (int) $diaryEntry->level;

        $ok = match ($level) {
            0 => $writer->regenerateEntry($diaryEntry),
            1 => $summarizer->regenerateSummary($diaryEntry),
            default => false,
        };

        if (! $ok) {
            abort(422, 'Не удалось пересоздать запись. Проверьте источник.');
        }

        $diaryEntry->refresh()->loadMissing('scene:id,title,game_session_id');

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

        return [
            'id' => (int) $entry->id,
            'character_id' => (int) $entry->character_id,
            'scene_id' => $entry->scene_id === null ? null : (int) $entry->scene_id,
            'scene_title' => $entry->scene?->title,
            'level' => (int) $entry->level,
            'from_message_id' => $entry->from_message_id === null ? null : (int) $entry->from_message_id,
            'to_message_id' => $entry->to_message_id === null ? null : (int) $entry->to_message_id,
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

    /**
     * @param  list<float>  $vector
     */
    private function formatVector(array $vector): string
    {
        return '['.implode(',', array_map(fn (float $v): string => (string) $v, $vector)).']';
    }
}