<?php

namespace App\Scene;

use App\Llm\ChatProvider;
use App\Models\LocationNode;
use App\Models\Scene;
use App\Models\SceneContext;
use App\Prompt\PromptRepository;
use App\Rag\EmbeddingProvider;
use App\Rag\VectorLiteral;
use App\Scene\Exceptions\EventGenerationFailedException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EventGeneratorService
{
    private const MIN_LOCATION_SIMILARITY = 0.45;

    public function __construct(
        private ChatProvider $chat,
        private EmbeddingProvider $embeddings,
        private PromptRepository $prompts,
        private SceneContextService $sceneContext,
    ) {}

    public function generate(
        Scene $scene,
        string $actionDescription,
        int $attackSuccesses,
        bool $attackBotch,
        bool $attackWillpower,
        int $defenseSuccesses,
        bool $defenseBotch,
        bool $defenseWillpower,
    ): string {
        $context = $this->sceneContext->current($scene);
        $situation = $this->promptField($context?->situation, 'No situation provided');
        $environment = $this->promptField($context?->environment_details, 'No details provided');
        $locationObjects = $this->buildLocationObjectsPrompt($context, $actionDescription);

        $system = $this->prompts->get('copilot.event.system', [
            'action_description' => $actionDescription,
            'situation' => $situation,
            'environment_details' => $environment,
            'location_objects' => $locationObjects,
            'attack_successes' => $attackSuccesses,
            'attack_botch' => $attackBotch ? 'true' : 'false',
            'attack_willpower' => $attackWillpower ? 'true' : 'false',
            'defense_successes' => $defenseSuccesses,
            'defense_botch' => $defenseBotch ? 'true' : 'false',
            'defense_willpower' => $defenseWillpower ? 'true' : 'false',
        ]);

        try {
            $turn = $this->chat->chatTurn(
                [
                    ['role' => 'system', 'content' => $system],
                ],
                [
                    'temperature' => (float) config('copilot.event_temperature'),
                    'max_tokens' => (int) config('copilot.event_max_output_tokens'),
                ],
            );
        } catch (\Throwable $e) {
            throw new EventGenerationFailedException($e);
        }

        $text = trim($turn->content);
        if ($text === '') {
            throw new EventGenerationFailedException;
        }

        return $text;
    }

    private function buildLocationObjectsPrompt(?SceneContext $context, string $actionDescription): string
    {
        $chronicleLocationId = $context?->chronicle_location_id;
        if ($chronicleLocationId === null) {
            return $this->environmentFallback($context);
        }

        $hasNodes = LocationNode::query()
            ->where('chronicle_location_id', $chronicleLocationId)
            ->exists();

        if (! $hasNodes) {
            return $this->environmentFallback($context);
        }

        $general = $this->generalLocationNodes((int) $chronicleLocationId);
        $specific = $this->specificLocationNodes((int) $chronicleLocationId, $actionDescription);

        if ($general->isEmpty() && $specific->isEmpty()) {
            return $this->environmentFallback($context);
        }

        if ($specific->isEmpty()) {
            return $this->generalWithRawEnvironment($general, $context);
        }

        $primarySpecific = $specific->first();
        $generalDisplay = $general->reject(
            fn (LocationNode $node): bool => (int) $primarySpecific->id === (int) $node->id,
        );

        $specificDisplay = $specific->reject(
            fn (LocationNode $node): bool => $generalDisplay->contains('id', $node->id),
        );

        $lines = [];

        if ($generalDisplay->isNotEmpty()) {
            $lines[] = '[General Environment]';
            foreach ($generalDisplay as $node) {
                $lines[] = $this->formatLocationNodeLine($node);
            }
        }

        if ($specificDisplay->isNotEmpty()) {
            $lines[] = '[Specific Objects]';
            foreach ($specificDisplay as $node) {
                $lines[] = $this->formatLocationNodeLine($node);
            }
        }

        return $lines === [] ? $this->environmentFallback($context) : implode("\n", $lines);
    }

    /**
     * @param  Collection<int, LocationNode>  $general
     */
    private function generalWithRawEnvironment(Collection $general, ?SceneContext $context): string
    {
        $lines = ['[General Environment]'];

        foreach ($general as $node) {
            $lines[] = $this->formatLocationNodeLine($node);
        }

        $raw = $this->rawEnvironment($context);
        if ($raw !== '') {
            $lines[] = $raw;
        }

        return implode("\n", $lines);
    }

    private function environmentFallback(?SceneContext $context): string
    {
        $raw = $this->rawEnvironment($context);

        return $raw !== '' ? $raw : 'No structured objects provided';
    }

    private function rawEnvironment(?SceneContext $context): string
    {
        if ($context === null) {
            return '';
        }

        return trim((string) ($context->environment_details ?? ''));
    }

    /**
     * @return Collection<int, LocationNode>
     */
    private function generalLocationNodes(int $chronicleLocationId): Collection
    {
        $roots = LocationNode::query()
            ->where('chronicle_location_id', $chronicleLocationId)
            ->whereNull('parent_id')
            ->orderBy('id')
            ->get();

        $nodes = collect();

        foreach ($roots as $root) {
            $nodes->push($root);

            $children = LocationNode::query()
                ->where('chronicle_location_id', $chronicleLocationId)
                ->where('parent_id', $root->id)
                ->orderBy('id')
                ->get();

            foreach ($children as $child) {
                $nodes->push($child);
            }
        }

        return $nodes->unique('id')->values();
    }

    /**
     * @return Collection<int, LocationNode>
     */
    private function specificLocationNodes(int $chronicleLocationId, string $actionDescription): Collection
    {
        $limit = max(1, (int) config('copilot.event_location_retrieval_limit', 5));
        $actionDescription = trim($actionDescription);
        if ($actionDescription === '') {
            return collect();
        }

        $vectorNodes = $this->vectorLocationNodes($chronicleLocationId, $actionDescription, $limit);
        $keywordNodes = $this->keywordLocationNodes($chronicleLocationId, $actionDescription, $limit);

        return $vectorNodes->concat($keywordNodes)->unique('id')->values();
    }

    /**
     * @return Collection<int, LocationNode>
     */
    private function vectorLocationNodes(int $chronicleLocationId, string $actionDescription, int $limit): Collection
    {
        $vector = $this->embeddings->embed($actionDescription);
        $pgVector = VectorLiteral::fromArray($vector);

        $rows = DB::select(
            'SELECT id
             FROM location_nodes
             WHERE chronicle_location_id = ?
               AND (1 - (embedding <=> ?::vector)) >= ?
             ORDER BY embedding <=> ?::vector
             LIMIT ?',
            [$chronicleLocationId, $pgVector, self::MIN_LOCATION_SIMILARITY, $pgVector, $limit],
        );

        return $this->nodesInRowOrder($rows);
    }

    /**
     * @return Collection<int, LocationNode>
     */
    private function keywordLocationNodes(int $chronicleLocationId, string $actionDescription, int $limit): Collection
    {
        $words = $this->actionKeywords($actionDescription);
        if ($words === []) {
            return collect();
        }

        $clauses = [];
        $bindings = [$chronicleLocationId];

        foreach ($words as $word) {
            $clauses[] = 'name ILIKE ?';
            $bindings[] = '%'.$word.'%';
        }

        $bindings[] = $limit;

        $rows = DB::select(
            'SELECT id
             FROM location_nodes
             WHERE chronicle_location_id = ?
               AND ('.implode(' OR ', $clauses).')
             ORDER BY id
             LIMIT ?',
            $bindings,
        );

        return $this->nodesInRowOrder($rows);
    }

    /**
     * «скамьей» не является подстрокой «Скамья»: к слову добавляется префикс без двух последних букв.
     *
     * @return list<string>
     */
    private function actionKeywords(string $actionDescription): array
    {
        $words = [];

        foreach (explode(' ', mb_strtolower($actionDescription)) as $part) {
            $word = preg_replace('/[^a-zа-яё]/u', '', $part);
            if (! is_string($word) || mb_strlen($word) <= 3) {
                continue;
            }

            $words[] = $word;
            $stem = mb_substr($word, 0, mb_strlen($word) - 2);
            if (mb_strlen($stem) > 3) {
                $words[] = $stem;
            }
        }

        return array_values(array_unique($words));
    }

    /**
     * @param  list<object>  $rows
     * @return Collection<int, LocationNode>
     */
    private function nodesInRowOrder(array $rows): Collection
    {
        if ($rows === []) {
            return collect();
        }

        $ids = array_map(fn (object $row): int => (int) $row->id, $rows);
        $byId = LocationNode::query()->whereIn('id', $ids)->get()->keyBy('id');

        $ordered = collect();
        foreach ($ids as $id) {
            $node = $byId->get($id);
            if ($node instanceof LocationNode) {
                $ordered->push($node);
            }
        }

        return $ordered;
    }

    private function formatLocationNodeLine(LocationNode $node): string
    {
        $description = trim((string) ($node->description ?? ''));

        return $description !== ''
            ? "- {$node->name}: {$description}"
            : "- {$node->name}";
    }

    private function promptField(mixed $value, string $fallback): string
    {
        $text = is_string($value) ? trim($value) : '';

        return $text === '' ? $fallback : $text;
    }
}
