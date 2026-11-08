<?php

namespace Tests\Feature;

use App\Canon\CanonLoreChunker;
use App\Context\TokenEstimator;
use App\Models\CanonLoreChunk;
use App\Models\CanonLoreEntry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class CanonChunkLoreCommandTest extends TestCase
{
    public function test_command_splits_large_lore_into_chunks_within_token_limit(): void
    {
        $this->fakeEmbeddings();

        $slug = 'test_large_lore_chunk';
        $entry = CanonLoreEntry::query()->create([
            'slug' => $slug,
            'title' => 'Test Large Lore',
            'text' => 'placeholder',
            'category' => 'test',
        ]);

        $paragraph = str_repeat('Длинный абзац про вампиров. ', 250);
        $body = "## История\n\n{$paragraph}\n\n{$paragraph}\n\n## Эпоха\n\n{$paragraph}";

        $dir = storage_path('framework/testing/canon-chunk-lore-'.uniqid());
        File::ensureDirectoryExists($dir);

        try {
            File::put($dir.'/article.md', "---\nslug: {$slug}\ntitle: Test\n---\n\n{$body}");

            $exit = Artisan::call('canon:chunk-lore', ['--path' => $dir]);
            $this->assertSame(0, $exit);

            $chunks = CanonLoreChunk::query()
                ->where('canon_lore_entry_id', $entry->id)
                ->orderBy('id')
                ->get();

            $this->assertGreaterThan(2, $chunks->count());

            $estimator = app(TokenEstimator::class);
            foreach ($chunks as $chunk) {
                $this->assertLessThanOrEqual(CanonLoreChunker::MAX_CHUNK_TOKENS, $chunk->token_estimate);
                $this->assertLessThanOrEqual(CanonLoreChunker::MAX_CHUNK_TOKENS, $estimator->estimate($chunk->content));
            }
        } finally {
            File::deleteDirectory($dir);
        }
    }

    public function test_chunker_splits_sections_by_headers(): void
    {
        $chunker = app(CanonLoreChunker::class);

        $chunks = $chunker->chunkMarkdownBody("## Первая\n\nКороткий текст.\n\n## Вторая\n\nЕщё короткий.");

        $this->assertCount(2, $chunks);
        $this->assertSame('Первая', $chunks[0]['header']);
        $this->assertSame('Вторая', $chunks[1]['header']);
    }
}
