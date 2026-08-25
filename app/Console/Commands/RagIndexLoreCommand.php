<?php

namespace App\Console\Commands;

use App\Rag\RagIndexer;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RagIndexLoreCommand extends Command
{
    protected $signature = 'rag:index-lore {title} {content} {--id=}';

    protected $description = 'Index a lore chunk for RAG (stub embeddings until Ollama)';

    public function handle(RagIndexer $indexer): int
    {
        $title = (string) $this->argument('title');
        $id = (string) ($this->option('id') ?: Str::slug($title));

        $chunk = $indexer->indexLore($id, $title, (string) $this->argument('content'));

        $this->info("Indexed lore chunk #{$chunk->id} ({$id}).");

        return self::SUCCESS;
    }
}
