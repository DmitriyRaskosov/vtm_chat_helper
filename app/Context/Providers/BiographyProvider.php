<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;

class BiographyProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
    ) {}

    public function key(): string
    {
        return 'biography';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $character = $assembly->character;
        if ($character === null) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_character']);
        }

        $biography = $character->biography()->first();
        if ($biography === null) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'empty',
            ]);
        }

        $priority = [
            'Summary' => $biography->summary,
            'Principles' => $biography->principles,
            'Motivation' => $biography->motivation,
            'Fears' => $biography->fears,
            'Desires' => $biography->desires,
            'Behavioral rules' => $biography->behavioral_rules,
            'Full text' => $biography->full_text,
        ];

        $lines = [];
        foreach ($priority as $label => $value) {
            if (! is_string($value) || trim($value) === '') {
                continue;
            }
            $lines[] = $label.': '.trim($value);
        }

        if ($lines === []) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'biography_version' => (int) $biography->current_version,
                'reason' => 'empty',
            ]);
        }

        [$content, $truncated] = $this->trimmer->prefix('## Biography', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => (int) $character->id,
            'biography_version' => (int) $biography->current_version,
            'status' => $biography->status->value,
        ], $truncated ? 'drop_full_text' : null);
    }
}
