<?php

namespace App\Diary;

use App\Diary\Exceptions\InvalidDiaryEntryInputException;
use App\Enums\DiaryEmotion;
use App\Models\CharacterDiaryEntry;
use App\Rag\EmbeddingProvider;
use App\Rag\VectorLiteral;

final class DiaryEntryEditor
{
    public function __construct(
        private EmbeddingProvider $embeddings,
        private DiaryWriterService $writer,
        private DiarySummarizerService $summarizer,
        private DiaryEntryRepository $entries,
    ) {}

    public function update(CharacterDiaryEntry $entry, string $text): CharacterDiaryEntry
    {
        $entryText = trim($text);
        if ($entryText === '') {
            throw new InvalidDiaryEntryInputException('Entry cannot be empty.');
        }

        $vector = $this->embeddings->embed($entryText);
        $emotion = $entry->getAttribute('emotion');

        $this->entries->updateContent(
            (int) $entry->id,
            $entryText,
            $entry->importance === null ? null : (float) $entry->importance,
            $emotion instanceof DiaryEmotion ? $emotion->value : null,
            VectorLiteral::fromArray($vector),
        );

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
