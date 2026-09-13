<?php

namespace App\Character;

use App\Context\TokenEstimator;
use App\Enums\CharacterBiographySection;
use App\Models\Character;
use App\Models\CharacterBioChunk;
use App\Models\CharacterBiography;
use App\Models\CharacterBiographyVersion;
use App\Rag\EmbeddingProvider;
use Illuminate\Support\Facades\DB;

class CharacterBioIndexer
{
    public const MAX_CHUNK_TOKENS = 400;

    public function __construct(
        private EmbeddingProvider $embeddings,
        private TokenEstimator $tokens,
    ) {}

    /**
     * Replace the character's bio index with chunks from a saved version.
     * Embeddings are computed before any rows are deleted so a failure leaves
     * both canon and the previous index unchanged.
     */
    public function indexVersion(CharacterBiographyVersion $version): int
    {
        $rows = [];
        $chunkIndex = 0;

        foreach (CharacterBiographySection::cases() as $section) {
            $text = trim((string) $version->{$section->value});

            if ($text === '') {
                continue;
            }

            foreach ($this->splitSection($text) as $part) {
                $rows[] = [
                    'biography_version_id' => $version->id,
                    'character_id' => $version->character_id,
                    'chunk_index' => $chunkIndex++,
                    'section' => $section,
                    'content' => $part,
                    'token_estimate' => $this->tokens->estimate($part),
                    'metadata' => [
                        'estimator' => $this->tokens->version(),
                        'biography_version' => $version->version,
                    ],
                    'embedding' => $this->embeddings->embed($part),
                ];
            }
        }

        return DB::transaction(function () use ($version, $rows): int {
            CharacterBioChunk::query()
                ->where('character_id', $version->character_id)
                ->delete();

            foreach ($rows as $row) {
                CharacterBioChunk::query()->create($row);
            }

            return count($rows);
        });
    }

    public function rebuildForCharacter(Character $character): int
    {
        $biography = CharacterBiography::query()->find($character->id);

        if ($biography === null) {
            CharacterBioChunk::query()->where('character_id', $character->id)->delete();

            return 0;
        }

        $version = CharacterBiographyVersion::query()
            ->where('character_id', $character->id)
            ->where('version', $biography->current_version)
            ->firstOrFail();

        return $this->indexVersion($version);
    }

    /**
     * @return list<string>
     */
    private function splitSection(string $content): array
    {
        if ($this->tokens->estimate($content) <= self::MAX_CHUNK_TOKENS) {
            return [$content];
        }

        $paragraphs = preg_split('/\n{2,}/', $content) ?: [$content];
        $chunks = [];
        $buffer = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            $candidate = $buffer === '' ? $paragraph : $buffer."\n\n".$paragraph;

            if ($buffer !== '' && $this->tokens->estimate($candidate) > self::MAX_CHUNK_TOKENS) {
                $chunks[] = $buffer;
                $buffer = $paragraph;
            } else {
                $buffer = $candidate;
            }
        }

        if ($buffer !== '') {
            $chunks[] = $buffer;
        }

        return $chunks === [] ? [$content] : $chunks;
    }
}
