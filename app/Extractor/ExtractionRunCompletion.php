<?php

namespace App\Extractor;

use App\Enums\ExtractionCandidateStatus;
use App\Enums\ExtractionRunStatus;
use App\Models\ExtractionRun;

final class ExtractionRunCompletion
{
    /**
     * @param  array<string, mixed>  $candidates
     */
    public static function hasPendingCandidates(array $candidates): bool
    {
        foreach (['mentions', 'relations', 'events', 'memories'] as $key) {
            foreach ($candidates[$key] ?? [] as $row) {
                if (! is_array($row)) {
                    continue;
                }

                if (($row['status'] ?? '') === ExtractionCandidateStatus::Pending->value) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function finalizeIfComplete(ExtractionRun $run): ExtractionRun
    {
        if ($run->status !== ExtractionRunStatus::NeedsReview) {
            return $run;
        }

        if (! self::hasPendingCandidates($run->candidates ?? [])) {
            $run->status = ExtractionRunStatus::Reviewed;
            $run->save();
        }

        return $run->refresh();
    }
}
