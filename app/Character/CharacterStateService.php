<?php

namespace App\Character;

use App\Enums\CharacterHealthDamage;
use App\Models\Character;
use App\Models\CharacterState;

final class CharacterStateService
{
    public function __construct(
        private CharacterStatsService $stats,
    ) {}

    public function ensure(Character $character): CharacterState
    {
        $loaded = $character->relationLoaded('state') ? $character->state : null;

        if ($loaded instanceof CharacterState) {
            return $loaded;
        }

        return CharacterState::query()->firstOrCreate(
            ['character_id' => $character->id],
            [
                'blood_pool' => 10,
                'temporary_willpower' => 5,
                'health_level' => 0,
                'health_damage' => CharacterHealthDamage::Bashing,
                'health_penalty' => 0,
            ],
        );
    }

    /**
     * @param  array{
     *     blood_pool: int,
     *     temporary_willpower: int,
     *     health_level: int,
     *     health_damage: CharacterHealthDamage|string
     * }  $fields
     * @return array{
     *     blood_pool: int,
     *     temporary_willpower: int,
     *     health_level: int,
     *     health_damage: string,
     *     health_penalty: int
     * }
     */
    public function apply(Character $character, array $fields): array
    {
        return $this->stats->applyLegacy($character, $fields);
    }

    public static function healthPenaltyForLevel(int $healthLevel): int
    {
        return CharacterStatsService::healthPenalty($healthLevel);
    }

    /**
     * @return array{
     *     blood_pool: int,
     *     temporary_willpower: int,
     *     health_level: int,
     *     health_damage: string,
     *     health_penalty: int
     * }
     */
    public function toArray(CharacterState $state): array
    {
        return [
            'blood_pool' => (int) $state->blood_pool,
            'temporary_willpower' => (int) $state->temporary_willpower,
            'health_level' => (int) $state->health_level,
            'health_damage' => $state->health_damage->value,
            'health_penalty' => (int) $state->health_penalty,
        ];
    }
}
