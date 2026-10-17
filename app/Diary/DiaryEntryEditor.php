<?php

namespace App\Diary;

use App\Diary\Exceptions\InvalidDiaryEntryInputException;
use App\Models\CharacterDiaryEntry;
use App\Rag\EmbeddingProvider;
use App\Rag\VectorLiteral;
use Illuminate\Support\Facades\DB;

final class DiaryEntryEditor
{
    public function __construct(
        private EmbeddingProvider $embeddings,
        private DiaryWriterService $writer,
        private DiarySummarizerService $summarizer,
    ) {}

    public function update(CharacterDiaryEntry $entry, string $text): CharacterDiaryEntry
    {
        $entryText = trim($text);
        if ($entryText === '') {
            throw new InvalidDiaryEntryInputException('Entry cannot be empty.');
        }

        $vector = $this->embeddings->embed($entryText);

        DB::transaction(function () use ($entry, $entryText, $vector): void {
            $entry->update(['entry' => $entryText]);

            DB::statement(
                'UPDATE character_diary_entries SET embedding = ?::vector WHERE id = ?',
                [VectorLiteral::fromArray($vector), $entry->id],
            );
        });

        return $entry->refresh()->loadMissing('scene:id,title,game_session_id');
    }

    public function regenerate(CharacterDiaryEntry $entry): CharacterDiaryEntry
    {
        $level = (int) $entry->level;

        $ok = match ($level) {
            0 => $this->writer->regenerateEntry($entry),
            1 => $this->summarizer->regenerateSummary($entry),
            default => false,
        };

        if (! $ok) {
            throw new InvalidDiaryEntryInputException('Не удалось пересоздать запись. Проверьте источник.');
        }

        return $entry->refresh()->loadMissing('scene:id,title,game_session_id');
    }

    public function destroy(CharacterDiaryEntry $entry): void
    {
        $entry->delete();
    }
}
