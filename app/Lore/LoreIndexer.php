<?php

namespace App\Lore;

use App\Context\TokenEstimator;
use App\Enums\LoreChunkSection;
use App\Enums\LoreEntryStatus;
use App\Models\LoreChunk;
use App\Models\LoreEntry;
use App\Models\LoreEntryVersion;
use App\Rag\EmbeddingProvider;
use App\Rag\TextChunker;
use Illuminate\Support\Facades\DB;

class LoreIndexer
{
    public function __construct(
        private EmbeddingProvider $embeddings,
        private TokenEstimator $tokens,
        private TextChunker $chunker,
    ) {}

    /**
     * Replace the entry's search index from a saved approved version.
     * Embeddings are computed before rows are deleted so a failure leaves
     * both canon and the previous index unchanged.
     */
    public function indexVersion(LoreEntryVersion $version): int
    {
        if ($version->status !== LoreEntryStatus::Approved) {
            return DB::transaction(function () use ($version): int {
                LoreChunk::query()->where('lore_entry_id', $version->lore_entry_id)->delete();

                return 0;
            });
        }

        $rows = [];
        $chunkIndex = 0;

        foreach ([
            LoreChunkSection::Title->value => $version->title,
            LoreChunkSection::CanonicalText->value => $version->canonical_text,
        ] as $section => $text) {
            foreach ($this->chunker->split((string) $text) as $part) {
                $rows[] = [
                    'lore_entry_version_id' => $version->id,
                    'lore_entry_id' => $version->lore_entry_id,
                    'chronicle_id' => $version->chronicle_id,
                    'chunk_index' => $chunkIndex++,
                    'section' => $section,
                    'content' => $part,
                    'token_estimate' => $this->tokens->estimate($part),
                    'visibility' => $version->visibility,
                    'metadata' => [
                        'estimator' => $this->tokens->version(),
                        'lore_version' => $version->version,
                    ],
                    'embedding' => $this->embeddings->embed($part),
                ];
            }
        }

        return DB::transaction(function () use ($version, $rows): int {
            LoreChunk::query()->where('lore_entry_id', $version->lore_entry_id)->delete();

            foreach ($rows as $row) {
                LoreChunk::query()->create($row);
            }

            return count($rows);
        });
    }

    public function rebuildApproved(LoreEntry $entry): int
    {
        $entry->refresh();

        if ($entry->status !== LoreEntryStatus::Approved) {
            LoreChunk::query()->where('lore_entry_id', $entry->id)->delete();

            return 0;
        }

        $version = LoreEntryVersion::query()
            ->where('lore_entry_id', $entry->id)
            ->where('version', $entry->current_version)
            ->firstOrFail();

        return $this->indexVersion($version);
    }
}
