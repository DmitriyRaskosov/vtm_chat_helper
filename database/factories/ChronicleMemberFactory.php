<?php

namespace Database\Factories;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\ChronicleMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChronicleMember>
 */
class ChronicleMemberFactory extends Factory
{
    protected $model = ChronicleMember::class;

    public function definition(): array
    {
        return [
            'chronicle_id' => Chronicle::factory(),
            'user_id' => User::factory(),
            'role' => ChronicleRole::Player,
            'joined_at' => now(),
            'left_at' => null,
        ];
    }

    public function storyteller(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ChronicleRole::Storyteller,
        ]);
    }

    public function player(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => ChronicleRole::Player,
        ]);
    }

    public function left(): static
    {
        return $this->state(fn (array $attributes) => [
            'left_at' => now(),
        ]);
    }
}
