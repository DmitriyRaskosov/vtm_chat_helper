<?php

namespace Database\Factories;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\ChronicleInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChronicleInvitation>
 */
class ChronicleInvitationFactory extends Factory
{
    protected $model = ChronicleInvitation::class;

    public function definition(): array
    {
        return [
            'chronicle_id' => Chronicle::factory(),
            'token_hash' => hash('sha256', fake()->uuid()),
            'role' => ChronicleRole::Player,
            'created_by' => User::factory(),
            'expires_at' => now()->addDay(),
            'max_uses' => null,
            'uses' => 0,
            'revoked_at' => null,
        ];
    }
}
