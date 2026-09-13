<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\TokenEstimator;

class SystemPromptProvider implements ContextProvider
{
    public function __construct(private TokenEstimator $estimator) {}

    public function key(): string
    {
        return 'system';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $npcName = $assembly->request->npcName;
        $draftCount = $assembly->request->draftCount;
        $tools = '';
        if (config('copilot.tools.enabled')) {
            $tools = <<<'PROMPT'

You may call search_messages or get_message_range to fetch extra session-scoped history. Tools never return the full transcript. After tools, respond with JSON drafts only.
PROMPT;
        }

        $content = <<<PROMPT
You are a storyteller assistant for a tabletop RPG text chat game.
Write exactly {$draftCount} different in-character reply drafts for the NPC "{$npcName}".
Each draft is one chat message only — no narration labels, no quotes around the whole line, no meta commentary.
Use only the supplied context blocks. Do not invent unknown lore, memories, or rules.
{$tools}
Respond with valid JSON only, no markdown fences:
{"drafts":["first reply","second reply","third reply"]}
PROMPT;

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'npc_name' => $npcName,
            'draft_count' => $draftCount,
            'tools_enabled' => (bool) config('copilot.tools.enabled'),
        ]);
    }
}
