<?php

namespace App\Jobs;

use App\Models\Message;
use App\Rag\RagIndexer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IndexRagMessageJob implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $messageId) {}

    public function handle(RagIndexer $indexer): void
    {
        $message = Message::query()->find($this->messageId);

        if ($message === null) {
            return;
        }

        $indexer->indexMessage($message);
    }
}
