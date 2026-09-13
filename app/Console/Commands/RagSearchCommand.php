<?php

namespace App\Console\Commands;

use App\Rag\MessageSearcher;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class RagSearchCommand extends Command
{
    protected $signature = 'rag:search {chronicle_id} {query} {--limit=5}';

    protected $description = 'Search the chronicle-scoped message corpus';

    public function handle(MessageSearcher $searcher): int
    {
        $results = $searcher->search(
            (int) $this->argument('chronicle_id'),
            (string) $this->argument('query'),
            (int) $this->option('limit'),
        );

        if ($results->isEmpty()) {
            $this->warn('Ничего не найдено.');

            return self::SUCCESS;
        }

        foreach ($results as $result) {
            $this->line(sprintf(
                '[message/%s] %.4f %s',
                $result->message_id,
                $result->neighbor_distance ?? 0,
                Str::limit($result->content, 80),
            ));
        }

        return self::SUCCESS;
    }
}
