<?php

namespace Database\Factories;

use App\Enums\RulesetStatus;
use App\Models\Ruleset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Ruleset>
 */
class RulesetFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Vampire: The Masquerade',
            'edition' => 'v20-'.fake()->unique()->numerify('###'),
            'language' => 'ru',
            'status' => RulesetStatus::Published,
            'description' => null,
        ];
    }
}
