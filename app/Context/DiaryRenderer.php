<?php

namespace App\Context;

use App\Models\CharacterDiaryEntry;
use Illuminate\Support\Collection;

final class DiaryRenderer
{
    /**
     * Строит строки и список id ровно так, как это уйдёт в промпт.
     * Используется и DiaryProvider, и DiaryPreviewService.
     *
     * @param  Collection<int, CharacterDiaryEntry>  $entries
     * @return array{lines: list<string>, ids: list<int>}
     */
    public static function buildLines(Collection $entries): array
    {
        $lines = [];
        $ids = [];
        $isFirst = true;

        foreach ($entries as $entry) {
            $ids[] = (int) $entry->id;
            $label = self::labelFor($entry, $isFirst);

            $paragraphs = preg_split('/\n\s*\n/u', trim($entry->entry)) ?: [];
            $first = true;

            foreach ($paragraphs as $p) {
                $p = trim((string) $p);
                if ($p === '') {
                    continue;
                }
                $lines[] = $first ? $label.' '.$p : $p;
                $first = false;
            }

            $isFirst = false;
        }

        return ['lines' => $lines, 'ids' => $ids];
    }

    public static function labelFor(CharacterDiaryEntry $entry, bool $isFirst): string
    {
        $isL1 = (int) $entry->level === 1;

        if ($isL1) {
            return $isFirst ? '[diary — scene summary]' : '[diary — earlier scene summary]';
        }

        return $isFirst ? '[diary — most recent]' : '[diary — earlier]';
    }
}
