<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\TokenEstimator;

class ClosingInstructionProvider implements ContextProvider
{
    public function __construct(private TokenEstimator $estimator) {}

    public function key(): string
    {
        return 'closing';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $npcName = $assembly->request->npcName;
        $draftCount = $assembly->request->draftCount;
        $content = "Generate {$draftCount} distinct reply drafts for {$npcName}.";

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'npc_name' => $npcName,
            'draft_count' => $draftCount,
        ]);
    }
}
