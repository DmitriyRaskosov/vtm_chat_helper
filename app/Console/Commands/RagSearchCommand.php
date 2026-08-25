<?php

namespace App\Console\Commands;

use App\Enums\RagSourceType;
use App\Rag\RagSearcher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RagSearchCommand extends Command
{
    protected $signature = 'rag:search {query} {--type=} {--limit=5}';

    protected $description = 'Search RAG chunks (stub embeddings until Ollama)';

    public function handle(RagSearcher $searcher): int
    {
        $typeOption = $this->option('type');
        $types = $typeOption
            ? [RagSourceType::from($typeOption)]
            : null;

        $results = $searcher->search(
            (string) $this->argument('query'),
            (int) $this->option('limit'),
            $types,
        );

        if ($results->isEmpty()) {
            $this->warn('Ничего не найдено.');

            return self::SUCCESS;
        }

        foreach ($results as $chunk) {
            $this->line(sprintf(
                '[%s/%s] %.4f %s',
                $chunk->source_type->value,
                $chunk->source_id,
                $chunk->neighbor_distance ?? 0,
                Str::limit($chunk->content, 80),
            ));
        }

        return self::SUCCESS;
    }
}
