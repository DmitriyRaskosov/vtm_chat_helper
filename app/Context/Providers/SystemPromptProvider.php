<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextPass;
use App\Context\ContextSection;
use App\Context\TokenEstimator;
use App\Prompt\PromptRepository;

class SystemPromptProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private PromptRepository $prompts,
    ) {}

    public function key(): string
    {
        return 'system';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        if ($assembly->request->pass === ContextPass::Topics) {
            return $this->topics($assembly);
        }

        return $this->reply($assembly);
    }

    private function topics(ContextAssembly $assembly): ContextSection
    {
        $npcName = $assembly->request->npcName;
        $content = $this->prompts->get('copilot.topics.system', [
            'npcName' => $npcName,
        ]);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'npc_name' => $npcName,
            'pass' => ContextPass::Topics->value,
        ]);
    }

    private function reply(ContextAssembly $assembly): ContextSection
    {
        $npcName = $assembly->request->npcName;
        $draftCount = $assembly->request->draftCount;

        $content = $this->prompts->get('copilot.reply.system', [
            'npcName' => $npcName,
            'draftCount' => $draftCount,
        ]);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'npc_name' => $npcName,
            'draft_count' => $draftCount,
            'tools_enabled' => (bool) config('copilot.tools.enabled'),
            'pass' => ContextPass::Reply->value,
        ]);
    }
}
