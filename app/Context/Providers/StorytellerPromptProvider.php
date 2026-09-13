<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\TokenEstimator;

class StorytellerPromptProvider implements ContextProvider
{
    public function __construct(private TokenEstimator $estimator) {}

    public function key(): string
    {
        return 'storyteller_prompt';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $prompt = trim($assembly->request->prompt);
        $content = "## Storyteller prompt\n{$prompt}";

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'characters' => mb_strlen($prompt),
        ]);
    }
}
