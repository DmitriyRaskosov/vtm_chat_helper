<?php

namespace App\Console\Commands;

use App\Rag\EmbeddingProvider;
use Illuminate\Console\Command;

class RagEmbedPingCommand extends Command
{
    protected $signature = 'rag:embed-ping {text=ping}';

    protected $description = 'Request one embedding from the configured provider';

    public function handle(EmbeddingProvider $embeddings): int
    {
        $vector = $embeddings->embed((string) $this->argument('text'));
        $this->info('driver='.(string) config('rag.driver').' dim='.count($vector));

        return self::SUCCESS;
    }
}
