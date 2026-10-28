<?php

namespace Database\Factories;

use App\Enums\CharacterType;
use App\Enums\ChronicleRole;
use App\Enums\WorldEntityStatus;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\ChronicleMember;
use App\Models\User;
use App\Models\WorldEntity;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Character>
 */
class CharacterFactory extends Factory
{
    protected $model = Character::class;

    public function definition(): array
    {
        return [
            'chronicle_id' => Chronicle::factory(),
            'entity_type' => WorldEntityType::Character,
            'character_type' => CharacterType::Npc,
            'user_id' => null,
            'clan_id' => null,
            'sect_id' => null,
            'sire_character_id' => null,
            'generation' => null,
            'apparent_age' => null,
            'actual_age' => null,
            'nature' => null,
            'demeanor' => null,
            'concept' => null,
            'is_active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Character $character): void {
            if ($character->getKey() !== null) {
                return;
            }

            $chronicleId = (int) $character->chronicle_id;

            $canonicalName = (string) ($character->getAttributes()['canonical_name'] ?? fake()->unique()->name());
            if (array_key_exists('canonical_name', $character->getAttributes())) {
                $character->offsetUnset('canonical_name');
            }

            $entity = WorldEntity::query()->create([
                'chronicle_id' => $chronicleId,
                'entity_type' => WorldEntityType::Character,
                'canonical_name' => $canonicalName,
                'slug' => $this->uniqueSlug((int) $chronicleId, $canonicalName),
                'status' => WorldEntityStatus::Active,
            ]);

            $character->id = $entity->id;
            $character->entity_type = WorldEntityType::Character->value;

            if (
                $character->character_type === CharacterType::Player
                && $character->user_id !== null
            ) {
                ChronicleMember::query()->firstOrCreate(
                    [
                        'chronicle_id' => $chronicleId,
                        'user_id' => (int) $character->user_id,
                    ],
                    [
                        'role' => ChronicleRole::Player,
                        'joined_at' => now(),
                    ],
                );
            }
        });
    }

    public function npc(): static
    {
        return $this->state(fn (array $attributes) => [
            'character_type' => CharacterType::Npc,
            'user_id' => null,
        ]);
    }

    public function player(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user) {
            $user ??= User::factory()->create();

            return [
                'character_type' => CharacterType::Player,
                'user_id' => $user->id,
            ];
        });
    }

    public function forChronicle(Chronicle|int $chronicle): static
    {
        $chronicleId = $chronicle instanceof Chronicle ? $chronicle->id : $chronicle;

        return $this->state(fn (array $attributes) => [
            'chronicle_id' => $chronicleId,
        ]);
    }

    private function uniqueSlug(int $chronicleId, string $canonicalName): string
    {
        $base = Str::slug($canonicalName);
        $base = $base === '' ? 'entity' : $base;
        $candidate = $base;
        $suffix = 2;

        while (
            WorldEntity::query()
                ->where('chronicle_id', $chronicleId)
                ->where('slug', $candidate)
                ->exists()
        ) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
