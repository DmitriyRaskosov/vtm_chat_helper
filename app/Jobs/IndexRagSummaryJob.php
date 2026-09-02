<?php

namespace App\Jobs;

use App\Models\ContextSummary;
use App\Rag\RagIndexer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexRagSummaryJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 600;

    public int $timeout = 240;

    public int $tries = 3;

    public function __construct(public int $summaryId) {}

    public function uniqueId(): string
    {
        return (string) $this->summaryId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(RagIndexer $indexer): void
    {
        $summary = ContextSummary::query()->find($this->summaryId);
        if ($summary !== null) {
            $indexer->indexSummary($summary);
        }
    }
}
