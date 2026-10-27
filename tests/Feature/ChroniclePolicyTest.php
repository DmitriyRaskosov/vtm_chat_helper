<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class ChroniclePolicyTest extends TestCase
{
    use JoinsChronicle;

    public function test_non_member_cannot_view_chronicle(): void
    {
        $chronicle = Chronicle::factory()->create();
        $outsider = User::factory()->create();

        $this->assertFalse(Gate::forUser($outsider)->allows('view', $chronicle));
    }

    public function test_player_cannot_create_invitation(): void
    {
        $storyteller = User::factory()->create();
        $player = User::factory()->create();
        $chronicle = Chronicle::factory()->create(['created_by' => $storyteller->id]);
        $this->joinChronicle($storyteller, $chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($player, $chronicle, ChronicleRole::Player);

        $this->actingAs($player, 'sanctum')
            ->postJson("/api/chronicles/{$chronicle->id}/invitations", [
                'role' => ChronicleRole::Player->value,
                'expires_at' => now()->addDay()->toIso8601String(),
                'max_uses' => 1,
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Только рассказчик.');
    }

    public function test_chronicle_storyteller_can_create_invitation(): void
    {
        $storyteller = User::factory()->create();
        $chronicle = Chronicle::factory()->create(['created_by' => $storyteller->id]);
        $this->joinChronicle($storyteller, $chronicle, ChronicleRole::Storyteller);

        $this->actingAs($storyteller, 'sanctum')
            ->postJson("/api/chronicles/{$chronicle->id}/invitations", [
                'role' => ChronicleRole::Player->value,
                'expires_at' => now()->addDay()->toIso8601String(),
                'max_uses' => 1,
            ])
            ->assertStatus(201);
    }

    public function test_storyteller_of_other_chronicle_cannot_create_invitation(): void
    {
        $owner = User::factory()->create();
        $otherStoryteller = User::factory()->create();
        $chronicle = Chronicle::factory()->create(['created_by' => $owner->id]);
        $otherChronicle = Chronicle::factory()->create(['created_by' => $otherStoryteller->id]);
        $this->joinChronicle($owner, $chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($otherStoryteller, $otherChronicle, ChronicleRole::Storyteller);

        $this->actingAs($otherStoryteller, 'sanctum')
            ->postJson("/api/chronicles/{$chronicle->id}/invitations", [
                'role' => ChronicleRole::Player->value,
                'expires_at' => now()->addDay()->toIso8601String(),
                'max_uses' => 1,
            ])
            ->assertStatus(403)
            ->assertJsonPath('message', 'Только рассказчик.');
    }
}
