<?php

namespace Database\Factories;

use App\Enums\ChronicleStatus;
use App\Models\Chronicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Chronicle>
 */
class ChronicleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'description' => null,
            'setting' => null,
            'status' => ChronicleStatus::Active,
            'created_by' => null,
            'started_at' => now(),
            'archived_at' => null,
        ];
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => ChronicleStatus::Archived,
            'archived_at' => now(),
        ]);
    }
}
