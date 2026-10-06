<?php

namespace App\Console\Commands\Diary;

use App\Models\CharacterDiaryEntry;
use App\Rag\EmbeddingProvider;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReembedDiaryCommand extends Command
{
    protected $signature = 'diary:reembed
                            {--chunk=50 : How many records to process per batch}
                            {--dry-run : Show what would be done without writing}';

    protected $description = 'Recompute embeddings for all character_diary_entries with the current embedding model.';

    public function handle(EmbeddingProvider $embeddings): int
    {
        $chunkSize = max(1, (int) $this->option('chunk'));
        $dryRun = (bool) $this->option('dry-run');

        $this->info("Embedding model: {$embeddings->model()} ({$embeddings->dimensions()}d)");

        $total = CharacterDiaryEntry::query()->count();
        if ($total === 0) {
            $this->info('No diary entries to reembed.');

            return self::SUCCESS;
        }

        $this->info("Total entries: {$total}");
        $this->info("Chunk size: {$chunkSize}");

        if ($dryRun) {
            $this->warn('DRY RUN — nothing will be written.');
        }

        $processed = 0;
        $failed = 0;
        $startedAt = now();

        CharacterDiaryEntry::query()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($entries) use ($embeddings, $dryRun, &$processed, &$failed): void {
                $texts = $entries->pluck('entry')->all();

                try {
                    $vectors = $embeddings->embedBatch($texts);
                } catch (\Throwable $e) {
                    $this->error('Batch failed: '.$e->getMessage());
                    $failed += $entries->count();

                    return;
                }

                foreach ($entries as $index => $entry) {
                    $vector = $vectors[$index] ?? null;
                    if ($vector === null) {
                        $failed++;

                        continue;
                    }

                    if (! $dryRun) {
                        DB::statement(
                            'UPDATE character_diary_entries SET embedding = ?::vector, updated_at = NOW() WHERE id = ?',
                            [$this->formatVector($vector), $entry->id],
                        );
                    }

                    $processed++;
                }

                $this->output->write('.');
            });

        $this->newLine(2);
        $this->info("Processed: {$processed}");
        if ($failed > 0) {
            $this->warn("Failed: {$failed}");
        }
        $this->info("Elapsed: {$startedAt->diffInSeconds(now())}s");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @param  list<float>  $vector
     */
    private function formatVector(array $vector): string
    {
        return '['.implode(',', array_map(fn (float $v): string => (string) $v, $vector)).']';
    }
}