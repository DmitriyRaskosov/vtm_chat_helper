<?php

namespace App\Context\Providers;

use App\Character\CharacterStatsService;
use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\TokenEstimator;
use App\Enums\CharacterHealthLevel;

class CharacterStateProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
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

        $stats = is_array($character->stats) ? $character->stats : [];
        $bloodPool = CharacterStatsService::filledDots($stats['blood_pool'] ?? null, 10);
        $healthLevel = $this->healthLevel($stats);
        $healthPenalty = $this->healthPenalty($stats, $healthLevel);
        $healthLabel = CharacterHealthLevel::tryFrom($healthLevel)?->label() ?? 'Unknown';

        $lines = [
            'Blood pool: '.$bloodPool,
            sprintf('Health: %s (Penalty: %d)', $healthLabel, $healthPenalty),
        ];
        $content = "## Character state\n".implode("\n", $lines);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'blood_pool' => $bloodPool,
            'health_level' => $healthLevel,
            'health_penalty' => $healthPenalty,
        ]);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function healthLevel(array $stats): int
    {
        $health = $stats['health'] ?? null;
        if (is_array($health) && array_key_exists('level', $health)) {
            return (int) $health['level'];
        }

        return $this->filled($stats['health_level'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $stats
     */
    private function healthPenalty(array $stats, int $level): int
    {
        $health = $stats['health'] ?? null;
        if (is_array($health) && array_key_exists('penalty', $health)) {
            return (int) $health['penalty'];
        }

        if (array_key_exists('health_penalty', $stats)) {
            return (int) $stats['health_penalty'];
        }

        return match ($level) {
            0, 1 => 0,
            2, 3 => -1,
            4, 5 => -2,
            6 => -5,
            7 => -100,
            default => 0,
        };
    }

    private function filled(mixed $value): int
    {
        return CharacterStatsService::filledDots($value, 0);
    }
}
