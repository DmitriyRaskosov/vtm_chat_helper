<?php

namespace App\Llm;

use App\Enums\RagSourceType;
use App\Models\Message;
use App\Models\RagChunk;
use App\Rag\RagSearcher;
use Illuminate\Support\Collection;
use RuntimeException;

class NpcCopilotService
{
    public function __construct(
        private ChatProvider $chat,
        private RagSearcher $searcher,
    ) {}

    /**
     * @return list<string>
     */
    public function drafts(string $npcName, string $prompt, int $sceneId): array
    {
        $draftCount = (int) config('copilot.draft_count');
        $history = $this->loadHistory($sceneId);
        $ragChunks = $this->searcher->search(
            $prompt,
            (int) config('copilot.rag_limit'),
            [RagSourceType::Message],
        );

        $messages = [
            [
                'role' => 'system',
                'content' => $this->systemPrompt($npcName, $draftCount),
            ],
            [
                'role' => 'user',
                'content' => $this->userPrompt($npcName, $prompt, $history, $ragChunks),
            ],
        ];

        try {
            $raw = $this->chat->chat($messages);
        } catch (\Throwable $e) {
            throw new RuntimeException('Ollama is unavailable.', 0, $e);
        }

        return $this->parseDrafts($raw, $draftCount);
    }

    private function systemPrompt(string $npcName, int $draftCount): string
    {
        return <<<PROMPT
You are a storyteller assistant for a Vampire: The Masquerade text chat game.
Write exactly {$draftCount} different in-character reply drafts for the NPC "{$npcName}".
Each draft is one chat message only — no narration labels, no quotes around the whole line, no meta commentary.
Respond with valid JSON only, no markdown fences:
{"drafts":["first reply","second reply","third reply"]}
PROMPT;
    }

    /**
     * @param  Collection<int, Message>  $history
     * @param  Collection<int, RagChunk>  $ragChunks
     */
    private function userPrompt(
        string $npcName,
        string $prompt,
        Collection $history,
        Collection $ragChunks,
    ): string {
        $parts = ["NPC: {$npcName}", '', 'Storyteller prompt:', $prompt, ''];

        if ($ragChunks->isNotEmpty()) {
            $parts[] = 'Relevant past context (semantic search):';
            foreach ($ragChunks as $chunk) {
                $parts[] = '- '.$chunk->content;
            }
            $parts[] = '';
        }

        if ($history->isNotEmpty()) {
            $parts[] = 'Recent chat:';
            foreach ($history as $message) {
                $parts[] = $message->displayAuthor().': '.$message->body;
            }
            $parts[] = '';
        }

        $parts[] = "Generate {$this->draftCount()} distinct reply drafts for {$npcName}.";

        return implode("\n", $parts);
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
            ->limit((int) config('copilot.history_limit'))
            ->get()
            ->reverse()
            ->values();
    }

    private function draftCount(): int
    {
        return (int) config('copilot.draft_count');
    }

    /**
     * @return list<string>
     */
    private function parseDrafts(string $raw, int $expected): array
    {
        $json = $this->extractJson($raw);

        if ($json !== null) {
            $decoded = json_decode($json, true);

            if (is_array($decoded) && isset($decoded['drafts']) && is_array($decoded['drafts'])) {
                $drafts = array_values(array_filter(
                    array_map(fn ($item): string => is_string($item) ? trim($item) : '', $decoded['drafts']),
                    fn (string $item): bool => $item !== '',
                ));

                if (count($drafts) >= $expected) {
                    return array_slice($drafts, 0, $expected);
                }
            }
        }

        if (preg_match_all('/^\s*(?:\d+[\).\]]\s*|-\s*)(.+)$/m', $raw, $matches)) {
            $drafts = array_values(array_filter(array_map(trim(...), $matches[1]), fn (string $s): bool => $s !== ''));

            if (count($drafts) >= $expected) {
                return array_slice($drafts, 0, $expected);
            }
        }

        $trimmed = trim($raw);
        if ($trimmed !== '') {
            return array_fill(0, $expected, $trimmed);
        }

        throw new RuntimeException('Could not parse draft replies from the model response.');
    }

    private function extractJson(string $raw): ?string
    {
        $trimmed = trim($raw);

        if (str_starts_with($trimmed, '{')) {
            return $trimmed;
        }

        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $raw, $matches)) {
            return $matches[1];
        }

        if (preg_match('/(\{[^{}]*"drafts"\s*:\s*\[.*?\]\s*\})/s', $raw, $matches)) {
            return $matches[1];
        }

        return null;
    }
}
