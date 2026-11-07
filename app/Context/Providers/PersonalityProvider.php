<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;

class PersonalityProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
    ) {}

    public function key(): string
    {
        return 'personality';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $character = $assembly->character;
        if ($character === null) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_character']);
        }

        $traits = $character->sheetTraits();
        if ($traits === []) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'empty',
            ]);
        }

        $lines = [];
        foreach ($traits as $trait) {
            if (($trait['name'] ?? '') === 'title') {
                continue;
            }

            $raw = (string) ($trait['value'] ?? '');
            if (trim($raw) === '') {
                continue;
            }

            $label = trim((string) ($trait['label'] ?? ''));
            $lines[] = "[behavior] {$label}: {$raw}";
        }

        if ($lines === []) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'empty',
            ]);
        }

        [$content, $truncated] = $this->trimmer->prefix('## Personality', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => (int) $character->id,
            'trait_keys' => array_map(
                fn (array $trait): string => (string) ($trait['name'] ?? ''),
                $traits,
            ),
        ], $truncated ? 'lines_tail' : null);
    }
}
