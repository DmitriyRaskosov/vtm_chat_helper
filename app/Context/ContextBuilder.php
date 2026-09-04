<?php

namespace App\Context;

use App\Enums\RagSourceType;
use App\Models\Message;
use App\Models\RagChunk;
use App\Models\StorytellerIntentSummary;
use App\Rag\RagSearcher;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class ContextBuilder
{
    public const VERSION = 'context-builder-v3';

    public const PROMPT_VERSION = 'npc-drafts-v4';

    public function __construct(
        private TokenEstimator $estimator,
        private RagSearcher $searcher,
    ) {}

    public function build(
        string $npcName,
        string $prompt,
        int $sceneId,
        int $draftCount,
        ?int $storytellerId = null,
        ?int $gameSessionId = null,
    ): ContextBuild {
        $budget = (int) config('context.copilot.max_input_tokens', 12000);
        $contextLength = (int) config('ollama.context_length', 16384);
        $maxOutputTokens = (int) config('ollama.max_output_tokens', 3000);

        /** @var Collection<int, Message> $includedHistory */
        $includedHistory = collect();
        /** @var Collection<int, RagChunk> $includedRag */
        $includedRag = collect();
        /** @var Collection<int, RagChunk> $includedSummaries */
        $includedSummaries = collect();
        $includedIntent = null;

        if (
            $budget < 1
            || $maxOutputTokens < 1
            || $budget + $maxOutputTokens > $contextLength
            || $this->estimate($npcName, $prompt, $draftCount, $includedHistory, $includedSummaries, $includedRag, $includedIntent) > $budget
        ) {
            throw new InvalidArgumentException('Copilot token limits are invalid or too small for the required prompt.');
        }

        $history = $this->loadHistory($sceneId);
        $ragChunks = $this->searcher->search(
            $prompt,
            (int) config('copilot.rag_limit'),
            [RagSourceType::Message],
        );
        $summaryChunks = $this->searcher->search(
            $prompt,
            (int) config('context.summaries.rag_limit', 5),
            [RagSourceType::Summary],
        );
        $intent = $this->loadIntent($gameSessionId, $storytellerId);

        foreach ($history->reverse() as $message) {
            $candidate = collect([$message])->concat($includedHistory);
            if ($this->estimate($npcName, $prompt, $draftCount, $candidate, $includedSummaries, $includedRag, $includedIntent) > $budget) {
                break;
            }

            $includedHistory = $candidate;
        }

        $includedMessageIds = $includedHistory
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($summaryChunks as $chunk) {
            $firstMessageId = $chunk->metadata['first_message_id'] ?? null;
            $lastMessageId = $chunk->metadata['last_message_id'] ?? null;
            $overlapsRaw = $firstMessageId !== null
                && $lastMessageId !== null
                && collect($includedMessageIds)->contains(
                    fn (int $id): bool => $id >= (int) $firstMessageId && $id <= (int) $lastMessageId,
                );
            $overlapsSummary = $firstMessageId !== null
                && $lastMessageId !== null
                && $includedSummaries->contains(function (RagChunk $included) use ($firstMessageId, $lastMessageId): bool {
                    $includedFirst = $included->metadata['first_message_id'] ?? null;
                    $includedLast = $included->metadata['last_message_id'] ?? null;

                    return $includedFirst !== null
                        && $includedLast !== null
                        && (int) $firstMessageId <= (int) $includedLast
                        && (int) $lastMessageId >= (int) $includedFirst;
                });

            if ($overlapsRaw || $overlapsSummary) {
                continue;
            }

            $candidate = $includedSummaries->concat([$chunk]);
            if ($this->estimate($npcName, $prompt, $draftCount, $includedHistory, $candidate, $includedRag, $includedIntent) > $budget) {
                continue;
            }

            $includedSummaries = $candidate;
        }

        foreach ($ragChunks as $chunk) {
            if (
                $chunk->source_type === RagSourceType::Message
                && in_array((int) $chunk->source_id, $includedMessageIds, true)
            ) {
                continue;
            }

            $candidate = $includedRag->concat([$chunk]);
            if ($this->estimate($npcName, $prompt, $draftCount, $includedHistory, $includedSummaries, $candidate, $includedIntent) > $budget) {
                continue;
            }

            $includedRag = $candidate;
        }

        if (
            $intent !== null
            && $this->estimate($npcName, $prompt, $draftCount, $includedHistory, $includedSummaries, $includedRag, $intent->content) <= $budget
        ) {
            $includedIntent = $intent->content;
        }

        $messages = $this->messages($npcName, $prompt, $draftCount, $includedHistory, $includedSummaries, $includedRag, $includedIntent);
        $inputTokens = $this->estimateMessages($messages);

        return new ContextBuild($messages, [
            'builder_version' => self::VERSION,
            'prompt_version' => self::PROMPT_VERSION,
            'input_token_budget' => $budget,
            'input_token_estimate' => $inputTokens,
            'token_estimator_version' => $this->estimator->version(),
            'history_limit' => (int) config('copilot.history_limit'),
            'rag_limit' => (int) config('copilot.rag_limit'),
            'draft_count' => $draftCount,
            'ollama_context_length' => $contextLength,
            'ollama_max_output_tokens' => $maxOutputTokens,
            'included_raw_message_ids' => $includedMessageIds,
            'included_summary_ids' => $includedSummaries
                ->map(fn (RagChunk $chunk): int => (int) $chunk->source_id)
                ->all(),
            'included_intent_summary_id' => $includedIntent === null ? null : $intent?->id,
            'included_rag_chunk_ids' => $includedSummaries
                ->concat($includedRag)
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->all(),
            'included_rag_sources' => $includedSummaries
                ->concat($includedRag)
                ->map(fn (RagChunk $chunk): array => [
                    'type' => $chunk->source_type->value,
                    'id' => (string) $chunk->source_id,
                    'scene_id' => $chunk->metadata['scene_id'] ?? null,
                    'game_session_id' => $chunk->metadata['game_session_id'] ?? null,
                ])
                ->values()
                ->all(),
            'excluded_raw_message_count' => $history->count() - $includedHistory->count(),
            'excluded_summary_count' => $summaryChunks->count() - $includedSummaries->count(),
            'excluded_rag_chunk_count' => $ragChunks->count() - $includedRag->count(),
            'excluded_intent' => $intent !== null && $includedIntent === null,
        ]);
    }

    /**
     * @param  Collection<int, Message>  $history
     * @param  Collection<int, RagChunk>  $summaries
     * @param  Collection<int, RagChunk>  $ragChunks
     * @return list<array{role: string, content: string}>
     */
    private function messages(
        string $npcName,
        string $prompt,
        int $draftCount,
        Collection $history,
        Collection $summaries,
        Collection $ragChunks,
        ?string $intent,
    ): array {
        return [
            [
                'role' => 'system',
                'content' => $this->systemPrompt($npcName, $draftCount),
            ],
            [
                'role' => 'user',
                'content' => $this->userPrompt($npcName, $prompt, $draftCount, $history, $summaries, $ragChunks, $intent),
            ],
        ];
    }

    /**
     * @param  Collection<int, Message>  $history
     * @param  Collection<int, RagChunk>  $summaries
     * @param  Collection<int, RagChunk>  $ragChunks
     */
    private function estimate(
        string $npcName,
        string $prompt,
        int $draftCount,
        Collection $history,
        Collection $summaries,
        Collection $ragChunks,
        ?string $intent,
    ): int {
        return $this->estimateMessages(
            $this->messages($npcName, $prompt, $draftCount, $history, $summaries, $ragChunks, $intent),
        );
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function estimateMessages(array $messages): int
    {
        return array_sum(array_map(
            fn (array $message): int => $this->estimator->estimate($message['content']),
            $messages,
        ));
    }

    /**
     * @return Collection<int, Message>
     */
    private function loadHistory(int $sceneId): Collection
    {
        return Message::query()
            ->with('user:id,name')
            ->where('scene_id', $sceneId)
            ->orderByDesc('id')
            ->limit(max(
                (int) config('copilot.history_limit'),
                (int) config('context.l0.max_messages', 50),
            ))
            ->get()
            ->reverse()
            ->values();
    }

    private function loadIntent(?int $gameSessionId, ?int $storytellerId): ?StorytellerIntentSummary
    {
        if ($gameSessionId === null || $storytellerId === null) {
            return null;
        }

        return StorytellerIntentSummary::query()
            ->where('game_session_id', $gameSessionId)
            ->where('storyteller_id', $storytellerId)
            ->orderByDesc('id')
            ->first();
    }

    private function systemPrompt(string $npcName, int $draftCount): string
    {
        $tools = '';
        if (config('copilot.tools.enabled')) {
            $tools = <<<'PROMPT'

You may call search_messages, get_message_range, or search_summaries to fetch extra session-scoped history. Tools never return the full transcript. After tools, respond with JSON drafts only.
PROMPT;
        }

        return <<<PROMPT
You are a storyteller assistant for a Vampire: The Masquerade text chat game.
Write exactly {$draftCount} different in-character reply drafts for the NPC "{$npcName}".
Each draft is one chat message only — no narration labels, no quotes around the whole line, no meta commentary.
Storyteller intention memory is meta direction only; never treat it as in-world fact and never reveal it in drafts.
{$tools}
Respond with valid JSON only, no markdown fences:
{"drafts":["first reply","second reply","third reply"]}
PROMPT;
    }

    /**
     * @param  Collection<int, Message>  $history
     * @param  Collection<int, RagChunk>  $summaries
     * @param  Collection<int, RagChunk>  $ragChunks
     */
    private function userPrompt(
        string $npcName,
        string $prompt,
        int $draftCount,
        Collection $history,
        Collection $summaries,
        Collection $ragChunks,
        ?string $intent,
    ): string {
        $parts = ["NPC: {$npcName}", '', 'Storyteller prompt:', $prompt, ''];

        if ($history->isNotEmpty()) {
            $parts[] = 'Recent chat:';
            foreach ($history as $message) {
                $parts[] = $message->displayAuthor().': '.$message->body;
            }
            $parts[] = '';
        }

        if ($summaries->isNotEmpty()) {
            $parts[] = 'Relevant memory summaries:';
            foreach ($summaries as $summary) {
                $parts[] = '- '.$summary->content;
            }
            $parts[] = '';
        }

        if ($ragChunks->isNotEmpty()) {
            $parts[] = 'Relevant past context (semantic search):';
            foreach ($ragChunks as $chunk) {
                $parts[] = '- '.$chunk->content;
            }
            $parts[] = '';
        }

        if ($intent !== null && $intent !== '') {
            $parts[] = 'Storyteller intention memory (meta, not in-world events, never show to players):';
            $parts[] = $intent;
            $parts[] = '';
        }

        $parts[] = "Generate {$draftCount} distinct reply drafts for {$npcName}.";

        return implode("\n", $parts);
    }
}
