<?php

namespace App\Console\Commands;

use App\Models\Message;
use App\Rag\RagIndexer;
use Illuminate\Console\Command;

class RagReindexMessagesCommand extends Command
{
    protected $signature = 'rag:reindex-messages {--chunk=100 : Messages per batch}';

    protected $description = 'Rebuild RAG embeddings for all chat messages';

    public function handle(RagIndexer $indexer): int
    {
        $chunk = max(1, (int) $this->option('chunk'));
        $indexed = 0;

        Message::query()
            ->orderBy('id')
            ->chunkById($chunk, function ($messages) use ($indexer, &$indexed): void {
                foreach ($messages as $message) {
                    $indexer->indexMessage($message);
                    $indexed++;
                }

                $this->line("Indexed {$indexed} messages…");
            });

        $this->info("Reindexed {$indexed} messages.");

        return self::SUCCESS;
    }
}
