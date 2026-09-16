<?php

namespace App\Extractor;

use App\Enums\ExtractionRunStatus;
use App\Enums\ExtractionSourceType;
use App\Models\ExtractionRun;
use App\Models\LoreEntry;
use InvalidArgumentException;

class LoreExtractionWindowPlanner
{
    /**
     * @return array{
     *     from_char_offset: int,
     *     to_char_offset: int,
     *     slice_text: string
     * }|null
     */
    public function planNextWindow(LoreEntry $entry): ?array
    {
        $text = (string) $entry->canonical_text;
        $totalChars = mb_strlen($text);

        if ($totalChars === 0) {
            return null;
        }

        $from = $this->lastExtractedCharOffset($entry);
        if ($from >= $totalChars) {
            return null;
        }

        $maxChars = max(1, (int) config('extractor.profiles.lore.article_max_chars', 10000));
        $sliceText = mb_substr($text, $from, $maxChars);
        $to = $from + mb_strlen($sliceText);

        return [
            'from_char_offset' => $from,
            'to_char_offset' => $to,
            'slice_text' => $sliceText,
        ];
    }

    /**
     * @return array{
     *     from_char_offset: int,
     *     to_char_offset: int,
     *     total_chars: int,
     *     window_index: int,
     *     window_count: int
     * }
     */
    public function previewNextWindow(LoreEntry $entry): array
    {
        $text = (string) $entry->canonical_text;
        $totalChars = mb_strlen($text);
        $maxChars = max(1, (int) config('extractor.profiles.lore.article_max_chars', 10000));
        $from = $this->lastExtractedCharOffset($entry);

        if ($totalChars === 0) {
            return [
                'from_char_offset' => 0,
                'to_char_offset' => 0,
                'total_chars' => 0,
                'window_index' => 0,
                'window_count' => 0,
                'can_extract' => false,
            ];
        }

        if ($from >= $totalChars) {
            $from = 0;
        }

        $sliceText = mb_substr($text, $from, $maxChars);
        $to = $from + mb_strlen($sliceText);
        $windowCount = (int) ceil($totalChars / $maxChars);
        $windowIndex = (int) floor($from / $maxChars) + 1;

        return [
            'from_char_offset' => $from,
            'to_char_offset' => $to,
            'total_chars' => $totalChars,
            'window_index' => $windowIndex,
            'window_count' => $windowCount,
            'can_extract' => $this->planNextWindow($entry) !== null,
        ];
    }

    public function canExtractNext(LoreEntry $entry): bool
    {
        return $this->planNextWindow($entry) !== null;
    }

    public function lastExtractedCharOffset(LoreEntry $entry): int
    {
        $max = ExtractionRun::query()
            ->where('source_type', ExtractionSourceType::Lore)
            ->where('source_id', $entry->id)
            ->where('status', ExtractionRunStatus::Reviewed)
            ->whereNotNull('to_char_offset')
            ->max('to_char_offset');

        return is_numeric($max) ? (int) $max : 0;
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    public function assertWindowFits(array $messages, ExtractorTokenBudget $tokenBudget): void
    {
        if (! $tokenBudget->fits($messages, 'lore')) {
            throw new InvalidArgumentException(
                'Source slice is too large for the extractor context window. Shorten the text or catalog.',
            );
        }
    }
}
