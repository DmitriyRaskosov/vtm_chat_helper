<?php

namespace Database\Factories;

use App\Enums\SceneParticipantRole;
use App\Models\Character;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SceneParticipant>
 */
class SceneParticipantFactory extends Factory
{
    protected $model = SceneParticipant::class;

    public function definition(): array
    {
        return [
            'scene_id' => Scene::factory()->active(),
            'chronicle_id' => null,
            'character_id' => null,
            'role' => SceneParticipantRole::Npc,
            'visible' => true,
            'is_current' => true,
            'entered_at' => now(),
            'left_at' => null,
            'entered_message_id' => null,
            'left_message_id' => null,
            'last_diary_message_id' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (SceneParticipant $participant): void {
            $scene = Scene::query()->with('gameSession')->findOrFail($participant->scene_id);

            $chronicleId = $scene->gameSession->chronicle_id;
            $participant->chronicle_id = $chronicleId;

            $characterId = $participant->getAttributes()['character_id'] ?? null;
            if ($characterId === null) {
                $participant->character_id = Character::factory()
                    ->npc()
                    ->create(['chronicle_id' => $chronicleId])
                    ->id;
            }
        });
    }

    public function npc(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => SceneParticipantRole::Npc,
        ]);
    }

    public function player(?User $user = null): static
    {
        return $this->state(function (array $attributes) use ($user) {
            return [
                'role' => SceneParticipantRole::Player,
                'character_id' => Character::factory()->player($user),
            ];
        });
    }

    public function left(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => false,
            'left_at' => now(),
        ]);
    }
}
