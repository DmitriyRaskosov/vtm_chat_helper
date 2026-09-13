<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;
use App\Context\LineTrimmer;
use App\Context\TokenEstimator;
use App\Models\CharacterStatusEffect;
use App\Models\WorldEntity;

class StatusProvider implements ContextProvider
{
    public function __construct(
        private TokenEstimator $estimator,
        private LineTrimmer $trimmer,
    ) {}

    public function key(): string
    {
        return 'status';
    }

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection
    {
        $character = $assembly->character;
        if ($character === null) {
            return ContextSection::omitted($this->key(), ['reason' => 'no_character']);
        }

        $status = $character->status()->first();
        if ($status === null) {
            return ContextSection::omitted($this->key(), [
                'character_id' => (int) $character->id,
                'reason' => 'no_status',
            ]);
        }

        $locationName = null;
        if ($status->current_location_id !== null) {
            $locationName = WorldEntity::query()
                ->whereKey($status->current_location_id)
                ->value('canonical_name');
        }

        $lines = [
            'Hunger: '.$status->hunger,
            'Blood pool: '.$status->blood_pool,
            'Temporary willpower: '.$status->temporary_willpower,
            'Health: '.$status->health_state->value,
            'Fatigue: '.$status->fatigue,
        ];
        if (is_string($locationName) && $locationName !== '') {
            $lines[] = "Location: {$locationName}";
        }

        $effectIds = [];
        $effects = CharacterStatusEffect::query()
            ->where('character_id', $character->id)
            ->active()
            ->orderBy('id')
            ->get();
        foreach ($effects as $effect) {
            $effectIds[] = (int) $effect->id;
            $description = trim((string) $effect->description);
            $lines[] = 'Effect ('.$effect->effect_type->value.'): '.($description !== '' ? $description : 'unspecified');
        }

        [$content, $truncated] = $this->trimmer->prefix('## Status', $lines, $tokenBudget);

        return ContextSection::fromContent($this->key(), $content, $this->estimator, [
            'character_id' => (int) $character->id,
            'revision' => (int) $status->revision,
            'effect_ids' => $effectIds,
            'location_id' => $status->current_location_id !== null ? (int) $status->current_location_id : null,
        ], $truncated ? 'effects_tail' : null);
    }
}
