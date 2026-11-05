<?php

namespace App\Context;

use App\Models\CharacterDiaryEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
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
    public static function buildLines(Collection $entries, ?Carbon $currentInGameDate = null): array
    {
        $lines = [];
        $ids = [];
        $isFirst = true;

        foreach ($entries as $entry) {
            $ids[] = (int) $entry->id;
            $label = self::labelFor($entry, $isFirst, $currentInGameDate);

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

    public static function labelFor(
        CharacterDiaryEntry $entry,
        bool $isFirst,
        ?Carbon $currentInGameDate = null,
    ): string {
        $when = $entry->in_game_date ?? $entry->created_at;

        if ($currentInGameDate !== null && $when !== null) {
            $relative = Carbon::parse($when)
                ->locale('en')
                ->diffForHumans($currentInGameDate, [
                    'syntax' => CarbonInterface::DIFF_RELATIVE_TO_NOW,
                ]);

            return '[diary — '.$relative.']';
        }

        $isL1 = (int) $entry->level === 1;

        if ($isL1) {
            return $isFirst ? '[diary — scene summary]' : '[diary — earlier scene summary]';
        }

        return $isFirst ? '[diary — most recent]' : '[diary — earlier]';
    }
}
