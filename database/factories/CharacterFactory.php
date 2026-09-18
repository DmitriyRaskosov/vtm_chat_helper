<?php

namespace Database\Factories;

use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\User;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    public function definition(): array
    {
        return [
            'id' => WorldEntity::factory()->state(['entity_type' => WorldEntityType::Character]),
            'chronicle_id' => fn (array $attributes) => WorldEntity::query()
                ->findOrFail($attributes['id'])
                ->chronicle_id,
            'entity_type' => WorldEntityType::Character,
            'character_type' => CharacterType::Npc,
            'user_id' => null,
            'clan_id' => null,
            'sire_character_id' => null,
            'domitor_character_id' => null,
            'generation' => null,
            'apparent_age' => null,
            'actual_age' => null,
            'nature' => null,
            'demeanor' => null,
            'concept' => null,
            'lore_clearance_levels' => [0],
            'is_active' => true,
            'experience' => 0,
        ];
    }

    public function player(?User $user = null): static
    {
        return $this->state(fn () => [
            'character_type' => CharacterType::Player,
            'user_id' => $user?->id ?? User::factory(),
        ]);
    }

    public function ghoul(?Character $domitor = null): static
    {
        return $this->state(function () use ($domitor): array {
            $host = $domitor ?? Character::factory()->create();

            return [
                'id' => WorldEntity::factory()->state([
                    'entity_type' => WorldEntityType::Character,
                    'chronicle_id' => $host->chronicle_id,
                ]),
                'chronicle_id' => $host->chronicle_id,
                'character_type' => CharacterType::Ghoul,
                'user_id' => null,
                'domitor_character_id' => $host->id,
            ];
        });
    }
}
