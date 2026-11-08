<?php

namespace App\Console\Commands\Canon;

use App\Canon\CanonLoreChunker;
use App\Models\CanonLoreChunk;
use App\Models\CanonLoreEntry;
use App\Rag\EmbeddingProvider;
use App\Rag\VectorLiteral;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Spatie\YamlFrontMatter\YamlFrontMatter;

class CanonChunkLoreCommand extends Command
{
    protected $signature = 'canon:chunk-lore {--path= : Directory with .md files imported to canon_lore_entries}';

    protected $description = 'Split canon lore articles into embedded chunks for RAG retrieval';

    public function handle(CanonLoreChunker $chunker, EmbeddingProvider $embeddings): int
    {
        $path = $this->option('path') ?? resource_path('canon/lore');

        if (! File::isDirectory($path)) {
            $this->error("Directory not found: {$path}");

            return self::FAILURE;
        }

        $files = File::files($path);
        $processed = 0;
        $skipped = 0;

        foreach ($files as $file) {
            if ($file->getExtension() !== 'md') {
                continue;
            }

            $parsed = YamlFrontMatter::parseFile($file->getPathname());
            $matter = $parsed->matter();
            $slug = $matter['slug'] ?? null;

            if (! is_string($slug) || $slug === '') {
                $this->warn("Skip {$file->getFilename()}: missing slug");

                continue;
            }

            $entry = CanonLoreEntry::query()->where('slug', $slug)->first();
            if (! $entry) {
                $this->warn("Skip {$slug}: no canon_lore_entries row (run canon:import-lore first)");
                $skipped++;

                continue;
            }

            $chunks = $chunker->chunkMarkdownBody($parsed->body());
            if ($chunks === []) {
                CanonLoreChunk::query()->where('canon_lore_entry_id', $entry->id)->delete();
                $this->warn("Skip {$slug}: empty body");
                $skipped++;

                continue;
            }

            $texts = array_map(
                fn (array $chunk): string => $chunk['header'] !== ''
                    ? "## {$chunk['header']}\n\n{$chunk['content']}"
                    : $chunk['content'],
                $chunks,
            );

            try {
                $vectors = $embeddings->embedBatch($texts);
            } catch (\Throwable $e) {
                $this->error("Embedding failed for {$slug}: ".$e->getMessage());

                return self::FAILURE;
            }

            DB::transaction(function () use ($entry, $chunks, $vectors): void {
                CanonLoreChunk::query()->where('canon_lore_entry_id', $entry->id)->delete();

                foreach ($chunks as $index => $chunk) {
                    $vector = $vectors[$index] ?? null;
                    if ($vector === null) {
                        throw new \RuntimeException("Missing embedding vector for chunk {$index}");
                    }

                    DB::table('canon_lore_chunks')->insert([
                        'canon_lore_entry_id' => $entry->id,
                        'header' => $chunk['header'],
                        'content' => $chunk['content'],
                        'token_estimate' => $chunk['token_estimate'],
                        'embedding' => DB::raw("'".VectorLiteral::fromArray($vector)."'::vector"),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });

            $processed++;
            $this->info("Chunked: {$slug} (".count($chunks).' chunks)');
        }

        $this->info("Processed: {$processed}, skipped: {$skipped}");

        return self::SUCCESS;
    }
}
