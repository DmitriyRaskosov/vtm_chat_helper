<?php

namespace App\Context\Providers;

use App\Character\CharacterStateService;
use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\TokenEstimator;
use App\Enums\CharacterHealthLevel;

class CharacterStateProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private CharacterStateService $stateService,
    ) {}

    public function key(): string
    {
        return 'character_state';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $character = $assembly->character;
        if ($character === null) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_character']);
        }

        $state = $this->stateService->ensure($character);
        $healthLevel = CharacterHealthLevel::tryFrom((int) $state->health_level);
        $healthLabel = $healthLevel?->label() ?? 'Unknown';

        $lines = [
            'Blood pool: '.(int) $state->blood_pool,
            sprintf('Health: %s (Penalty: %d)', $healthLabel, (int) $state->health_penalty),
        ];
        $content = "## Character state\n".implode("\n", $lines);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'blood_pool' => (int) $state->blood_pool,
            'health_level' => (int) $state->health_level,
            'health_penalty' => (int) $state->health_penalty,
        ]);
    }
}
