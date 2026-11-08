<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\CanonLoreEntry;
use App\Models\Message;
use App\Models\WorldEntity;
use App\Rag\EmbeddingProvider;
use App\Rag\VectorLiteral;
use Illuminate\Support\Facades\DB;

class LoreRagProvider implements ContextProvider
{
    public const LORE_ENTITY_TYPE = 'world_entity';

    private const MESSAGE_SCAN_LIMIT = 15;

    private const CHUNK_LIMIT = 2;

    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
        private EmbeddingProvider $embeddings,
    ) {}

    public function key(): string
    {
        return 'lore_rag';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $scanText = $this->buildScanText($assembly);
        if ($scanText === '') {
            return ContextSection::omitted($this->key(), ['reason' => 'empty_text']);
        }

        $entityIds = $this->findMentionedEntityIds($assembly, $scanText);
        if ($entityIds === []) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_entity_mention']);
        }

        $entryIds = $this->resolveLoreEntryIds($entityIds);
        if ($entryIds === []) {
            return ContextSection::omitted($this->key(), [
                'reason' => 'no_lore_entries',
                'world_entity_ids' => $entityIds,
            ]);
        }

        try {
            $chunks = $this->retrieveChunks($scanText, $entryIds);
        } catch (\Throwable $e) {
            return ContextSection::omitted($this->key(), [
                'reason' => 'retrieval_failed',
                'error' => $e->getMessage(),
            ]);
        }

        if ($chunks === []) {
            return ContextSection::omitted($this->key(), [
                'reason' => 'no_chunks',
                'lore_entry_ids' => $entryIds,
            ]);
        }

        $titles = CanonLoreEntry::query()
            ->whereIn('id', array_column($chunks, 'canon_lore_entry_id'))
            ->pluck('title', 'id');

        $lines = [];
        $chunkIds = [];
        foreach ($chunks as $row) {
            $entryId = (int) $row['canon_lore_entry_id'];
            $title = (string) ($titles[$entryId] ?? 'Lore');
            $header = (string) $row['header'];
            $label = $header !== '' ? "{$title} - {$header}" : $title;
            $lines[] = "[Lore: {$label}]\n".(string) $row['content'];
            $chunkIds[] = (int) $row['id'];
        }

        [$content, $truncated] = $this->trimmer->prefix('', $lines, $tokenBudget);

        if ($content === '') {
            return ContextSection::omitted($this->key(), [
                'reason' => 'budget',
                'chunk_ids' => $chunkIds,
            ]);
        }

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'world_entity_ids' => $entityIds,
            'lore_entry_ids' => $entryIds,
            'chunk_ids' => $chunkIds,
        ], $truncated ? 'line_budget' : null);
    }

    private function buildScanText(ContextAssembly $assembly): string
    {
        $parts = [];
        $prompt = trim($assembly->request->prompt);
        if ($prompt !== '') {
            $parts[] = $prompt;
        }

        $messages = Message::query()
            ->where('scene_id', $assembly->scene->id)
            ->where('is_ooc', false)
            ->whereNull('deleted_at')
            ->orderByDesc('id')
            ->limit(self::MESSAGE_SCAN_LIMIT)
            ->pluck('body');

        foreach ($messages as $body) {
            if (is_string($body) && trim($body) !== '') {
                $parts[] = trim($body);
            }
        }

        return trim(implode("\n", $parts));
    }

    /**
     * @return list<int>
     */
    private function findMentionedEntityIds(ContextAssembly $assembly, string $scanText): array
    {
        $entities = WorldEntity::query()
            ->active()
            ->where('chronicle_id', $assembly->chronicle->id)
            ->get(['id', 'canonical_name']);

        $matched = [];
        $haystack = mb_strtolower($scanText);

        foreach ($entities as $entity) {
            $name = trim((string) $entity->canonical_name);
            if ($name === '') {
                continue;
            }

            if (mb_strpos($haystack, mb_strtolower($name)) !== false) {
                $matched[] = (int) $entity->id;
            }
        }

        return array_values(array_unique($matched));
    }

    /**
     * @param  list<int>  $entityIds
     * @return list<int>
     */
    private function resolveLoreEntryIds(array $entityIds): array
    {
        if ($entityIds === []) {
            return [];
        }

        return DB::table('canon_lore_entry_entities')
            ->where('entity_type', self::LORE_ENTITY_TYPE)
            ->whereIn('entity_id', $entityIds)
            ->pluck('lore_entry_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $entryIds
     * @return list<array{id: int, canon_lore_entry_id: int, header: string, content: string}>
     */
    private function retrieveChunks(string $queryText, array $entryIds): array
    {
        if ($entryIds === []) {
            return [];
        }

        $vector = $this->embeddings->embed($queryText);
        $pgVector = VectorLiteral::fromArray($vector);
        $pgArray = '{'.implode(',', $entryIds).'}';

        $rows = DB::select(
            'SELECT id, canon_lore_entry_id, header, content
             FROM canon_lore_chunks
             WHERE canon_lore_entry_id = ANY(?::bigint[])
             ORDER BY embedding <=> ?::vector
             LIMIT ?',
            [$pgArray, $pgVector, self::CHUNK_LIMIT],
        );

        $chunks = [];
        foreach ($rows as $row) {
            $chunks[] = [
                'id' => (int) $row->id,
                'canon_lore_entry_id' => (int) $row->canon_lore_entry_id,
                'header' => (string) $row->header,
                'content' => (string) $row->content,
            ];
        }

        return $chunks;
    }
}
