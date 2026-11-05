<?php

namespace App\Scene;

use App\Llm\ChatProvider;
use App\Models\ChronicleLocation;
use App\Models\LocationNode;
use App\Prompt\PromptRepository;
use App\Rag\EmbeddingProvider;
use App\Rag\VectorLiteral;
use App\Scene\Exceptions\LocationNodeParseFailedException;
use Illuminate\Support\Facades\DB;

final class LocationNodeParserService
{
    public function __construct(
        private ChatProvider $chat,
        private EmbeddingProvider $embeddings,
        private PromptRepository $prompts,
    ) {}

    public function parse(ChronicleLocation $location, string $environmentDetails): void
    {
        $environmentDetails = trim($environmentDetails);
        if ($environmentDetails === '') {
            throw new LocationNodeParseFailedException;
        }

        $system = $this->prompts->get('scene_parser.system', [
            'environment_details' => $environmentDetails,
        ]);

        try {
            $turn = $this->chat->chatTurn(
                [
                    ['role' => 'system', 'content' => $system],
                ],
                [
                    'temperature' => (float) config('copilot.scene_parser_temperature'),
                    'max_tokens' => (int) config('copilot.scene_parser_max_output_tokens'),
                    'json_object' => true,
                ],
            );
        } catch (\Throwable $e) {
            throw new LocationNodeParseFailedException($e);
        }

        $nodes = $this->decodeNodes($turn->content);
        $flat = [];
        $this->flattenDecodedTree($nodes, null, $flat);

        if ($flat === []) {
            throw new LocationNodeParseFailedException;
        }

        $texts = array_column($flat, 'embedding_text');
        $vectors = $this->embeddings->embedBatch($texts);

        foreach ($flat as $index => $row) {
            $flat[$index]['vector'] = $vectors[$index] ?? $this->embeddings->embed($row['embedding_text']);
        }

        DB::transaction(function () use ($location, $flat): void {
            LocationNode::query()->where('chronicle_location_id', $location->id)->delete();

            $idsByIndex = [];
            foreach ($flat as $index => $row) {
                $parentId = $row['parent_index'] === null ? null : ($idsByIndex[$row['parent_index']] ?? null);

                $idsByIndex[$index] = (int) DB::table('location_nodes')->insertGetId([
                    'chronicle_location_id' => $location->id,
                    'parent_id' => $parentId,
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'embedding' => DB::raw("'".VectorLiteral::fromArray($row['vector'])."'::vector"),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function decodeNodes(string $raw): array
    {
        $json = trim($raw);
        if (! str_starts_with($json, '{') && preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $raw, $matches)) {
            $json = $matches[1];
        }

        $decoded = json_decode($json, true);
        if (! is_array($decoded) || ! isset($decoded['nodes']) || ! is_array($decoded['nodes'])) {
            throw new LocationNodeParseFailedException;
        }

        return $decoded['nodes'];
    }

    /**
     * @param  list<mixed>  $nodes
     * @param  list<array{parent_index: ?int, name: string, description: ?string, embedding_text: string}>  $flat
     */
    private function flattenDecodedTree(array $nodes, ?int $parentIndex, array &$flat): void
    {
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $name = trim((string) ($node['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $description = trim((string) ($node['description'] ?? ''));
            $embeddingText = $description !== '' ? "{$name}: {$description}" : $name;
            $children = $node['children'] ?? [];
            if (! is_array($children)) {
                $children = [];
            }

            $index = count($flat);
            $flat[] = [
                'parent_index' => $parentIndex,
                'name' => $name,
                'description' => $description !== '' ? $description : null,
                'embedding_text' => $embeddingText,
            ];

            if ($children !== []) {
                $this->flattenDecodedTree($children, $index, $flat);
            }
        }
    }
}
