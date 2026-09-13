<?php

namespace App\Jobs;

use App\Models\Message;
use App\Rag\RagIndexer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexRagMessageJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $uniqueFor = 60;

    public function __construct(public int $messageId) {}

    public function uniqueId(): string
    {
        return (string) $this->messageId;
    }

    public function handle(RagIndexer $indexer): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message === null) {
            return;
        }

        $indexer->indexMessage($message);
    }
}
