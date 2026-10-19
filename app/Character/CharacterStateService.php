<?php

namespace App\Character;

use App\Enums\CharacterHealthDamage;
use App\Models\Character;
use App\Models\CharacterState;
use Illuminate\Support\Facades\DB;

final class CharacterStateService
{
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
     */
    public function apply(Character $character, array $fields): CharacterState
    {
        $damage = $fields['health_damage'] instanceof CharacterHealthDamage
            ? $fields['health_damage']
            : CharacterHealthDamage::from((string) $fields['health_damage']);

        $healthLevel = (int) $fields['health_level'];

        return DB::transaction(function () use ($character, $fields, $damage, $healthLevel): CharacterState {
            $state = $this->ensure($character);

            $state->blood_pool = (int) $fields['blood_pool'];
            $state->temporary_willpower = (int) $fields['temporary_willpower'];
            $state->health_level = $healthLevel;
            $state->health_damage = $damage;
            $state->health_penalty = self::healthPenaltyForLevel($healthLevel);
            $state->save();

            return $state->refresh();
        });
    }

    public static function healthPenaltyForLevel(int $healthLevel): int
    {
        return match ($healthLevel) {
            0, 1 => 0,
            2, 3 => -1,
            4, 5 => -2,
            6 => -5,
            7 => -100,
            default => 0,
        };
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
