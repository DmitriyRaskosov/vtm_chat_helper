<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\ChronicleInvitation;
use App\Models\ChronicleMember;
use App\Models\User;
use Tests\TestCase;

class ChronicleInvitationTest extends TestCase
{
    public function test_guest_cannot_create_revoke_or_accept(): void
    {
        $storyteller = User::factory()->create();
        $chronicle = Chronicle::factory()->create(['created_by' => $storyteller->id]);
        $invitation = ChronicleInvitation::factory()->create([
            'chronicle_id' => $chronicle->id,
            'created_by' => $storyteller->id,
        ]);

        $this->postJson("/api/chronicles/{$chronicle->id}/invitations", $this->payload())
            ->assertStatus(401);
        $this->deleteJson("/api/chronicles/{$chronicle->id}/invitations/{$invitation->id}")
            ->assertStatus(401);
        $this->postJson('/api/invitations/not-a-token/accept')
            ->assertStatus(401);
    }

    public function test_storyteller_create_returns_token_once_and_stores_only_hash(): void
    {
        $storyteller = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);

        $response = $this->actingAs($storyteller, 'sanctum')
            ->postJson("/api/chronicles/{$chronicleId}/invitations", $this->payload());

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'invitation' => ['id', 'token', 'expires_at', 'max_uses', 'role'],
        ]);
        $this->assertSame(
            ['id', 'token', 'expires_at', 'max_uses', 'role'],
            array_keys($response->json('invitation')),
        );
        $response->assertJsonPath('invitation.role', ChronicleRole::Player->value);
        $response->assertJsonPath('invitation.max_uses', 5);

        $token = (string) $response->json('invitation.token');
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_-]+$/', $token);

        $stored = ChronicleInvitation::query()->findOrFail($response->json('invitation.id'));
        $this->assertSame(hash('sha256', $token), $stored->token_hash);
        $this->assertNotSame($token, $stored->token_hash);
        $this->assertSame(0, $stored->uses);
        $this->assertNull($stored->revoked_at);
    }

    public function test_storyteller_can_list_invitations(): void
    {
        $storyteller = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        $invitation = $this->createInvitation($storyteller, $chronicleId);

        $response = $this->actingAs($storyteller, 'sanctum')
            ->getJson("/api/chronicles/{$chronicleId}/invitations");

        $response->assertOk();
        $response->assertJsonStructure([
            'invitations' => [
                ['id', 'expires_at', 'max_uses', 'role'],
            ],
        ]);
        $response->assertJsonPath('invitations.0.id', $invitation['id']);
        $response->assertJsonPath('invitations.0.role', ChronicleRole::Player->value);
    }

    public function test_player_cannot_list_invitations(): void
    {
        $storyteller = User::factory()->create();
        $player = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        ChronicleMember::factory()->player()->create([
            'chronicle_id' => $chronicleId,
            'user_id' => $player->id,
        ]);
        $this->createInvitation($storyteller, $chronicleId);

        $this->actingAs($player, 'sanctum')
            ->getJson("/api/chronicles/{$chronicleId}/invitations")
            ->assertStatus(403)
            ->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_index_excludes_revoked_and_expired(): void
    {
        $storyteller = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        $active = $this->createInvitation($storyteller, $chronicleId);
        $revoked = $this->createInvitation($storyteller, $chronicleId);
        $expired = $this->createInvitation($storyteller, $chronicleId);

        $this->actingAs($storyteller, 'sanctum')
            ->deleteJson("/api/chronicles/{$chronicleId}/invitations/{$revoked['id']}")
            ->assertNoContent();

        ChronicleInvitation::query()->whereKey($expired['id'])->update([
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($storyteller, 'sanctum')
            ->getJson("/api/chronicles/{$chronicleId}/invitations");

        $response->assertOk();
        $response->assertJsonCount(1, 'invitations');
        $response->assertJsonPath('invitations.0.id', $active['id']);
    }

    public function test_player_cannot_create_or_revoke(): void
    {
        $storyteller = User::factory()->create();
        $player = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        ChronicleMember::factory()->player()->create([
            'chronicle_id' => $chronicleId,
            'user_id' => $player->id,
        ]);
        $invitation = $this->createInvitation($storyteller, $chronicleId);

        $this->actingAs($player, 'sanctum')
            ->postJson("/api/chronicles/{$chronicleId}/invitations", $this->payload())
            ->assertStatus(403)
            ->assertJsonPath('message', 'Только рассказчик.');

        $this->actingAs($player, 'sanctum')
            ->deleteJson("/api/chronicles/{$chronicleId}/invitations/{$invitation['id']}")
            ->assertStatus(403)
            ->assertJsonPath('message', 'Только рассказчик.');
    }

    public function test_storyteller_revokes_invitation(): void
    {
        $storyteller = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        $invitation = $this->createInvitation($storyteller, $chronicleId);

        $this->actingAs($storyteller, 'sanctum')
            ->deleteJson("/api/chronicles/{$chronicleId}/invitations/{$invitation['id']}")
            ->assertNoContent();

        $stored = ChronicleInvitation::query()->findOrFail($invitation['id']);
        $this->assertNotNull($stored->revoked_at);
    }

    public function test_revoke_of_invitation_from_another_chronicle_is_not_found(): void
    {
        $owner = User::factory()->create();
        $otherStoryteller = User::factory()->create();
        $ownChronicleId = $this->createChronicle($owner);
        $otherChronicleId = $this->createChronicle($otherStoryteller);
        $invitation = $this->createInvitation($owner, $ownChronicleId);

        $this->actingAs($otherStoryteller, 'sanctum')
            ->deleteJson("/api/chronicles/{$otherChronicleId}/invitations/{$invitation['id']}")
            ->assertStatus(404)
            ->assertJsonPath('message', 'Invitation not found.');

        $this->assertNull(ChronicleInvitation::query()->findOrFail($invitation['id'])->revoked_at);
    }

    public function test_accept_returns_chronicle_id_and_creates_membership(): void
    {
        $storyteller = User::factory()->create();
        $joiner = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        $invitation = $this->createInvitation($storyteller, $chronicleId);

        $response = $this->actingAs($joiner, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept');

        $response->assertOk();
        $this->assertSame(
            ['chronicle_id', 'role', 'joined_at'],
            array_keys($response->json()),
        );
        $response->assertJsonPath('chronicle_id', $chronicleId);
        $this->assertIsInt($response->json('chronicle_id'));
        $response->assertJsonPath('role', ChronicleRole::Player->value);
        $this->assertArrayNotHasKey('token', $response->json());

        $this->assertDatabaseHas('chronicle_members', [
            'chronicle_id' => $chronicleId,
            'user_id' => $joiner->id,
            'role' => ChronicleRole::Player->value,
            'left_at' => null,
        ]);
        $this->assertSame(1, ChronicleInvitation::query()->findOrFail($invitation['id'])->uses);
    }

    public function test_unknown_token_is_not_found(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/invitations/missing-token/accept')
            ->assertStatus(404)
            ->assertJsonPath('message', 'Invitation not found.');
    }

    public function test_expired_invitation_is_conflict_and_does_not_create_membership(): void
    {
        $storyteller = User::factory()->create();
        $joiner = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        $invitation = $this->createInvitation($storyteller, $chronicleId);
        ChronicleInvitation::query()->whereKey($invitation['id'])->update([
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($joiner, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept');

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Invitation has expired.');
        $this->assertArrayNotHasKey('chronicle_id', $response->json());

        $this->assertDatabaseMissing('chronicle_members', [
            'user_id' => $joiner->id,
        ]);
        $this->assertSame(0, ChronicleInvitation::query()->findOrFail($invitation['id'])->uses);
    }

    public function test_revoked_invitation_is_conflict(): void
    {
        $storyteller = User::factory()->create();
        $joiner = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        $invitation = $this->createInvitation($storyteller, $chronicleId);

        $this->actingAs($storyteller, 'sanctum')
            ->deleteJson("/api/chronicles/{$chronicleId}/invitations/{$invitation['id']}")
            ->assertNoContent();

        $response = $this->actingAs($joiner, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept');

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Invitation has been revoked.');
        $this->assertArrayNotHasKey('chronicle_id', $response->json());

        $this->assertDatabaseMissing('chronicle_members', [
            'user_id' => $joiner->id,
        ]);
    }

    public function test_revoked_invitation_for_existing_member_returns_chronicle_id(): void
    {
        $storyteller = User::factory()->create();
        $joiner = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        $invitation = $this->createInvitation($storyteller, $chronicleId);

        $this->actingAs($joiner, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept')
            ->assertOk();

        $this->actingAs($storyteller, 'sanctum')
            ->deleteJson("/api/chronicles/{$chronicleId}/invitations/{$invitation['id']}")
            ->assertNoContent();

        $response = $this->actingAs($joiner, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept');

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Invitation has been revoked.');
        $response->assertJsonPath('chronicle_id', $chronicleId);
        $this->assertIsInt($response->json('chronicle_id'));
        $this->assertSame(1, ChronicleInvitation::query()->findOrFail($invitation['id'])->uses);
    }

    public function test_exhausted_invitation_is_conflict(): void
    {
        $storyteller = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        $invitation = $this->createInvitation($storyteller, $chronicleId, ['max_uses' => 1]);

        $this->actingAs($first, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept')
            ->assertOk();

        $response = $this->actingAs($second, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept');

        $response->assertStatus(409);
        $response->assertJsonPath('message', 'Invitation has reached its use limit.');
        $this->assertArrayNotHasKey('chronicle_id', $response->json());

        $this->assertSame(1, ChronicleInvitation::query()->findOrFail($invitation['id'])->uses);
        $this->assertDatabaseMissing('chronicle_members', [
            'user_id' => $second->id,
        ]);
    }

    public function test_active_member_accept_is_conflict_with_chronicle_id(): void
    {
        $storyteller = User::factory()->create();
        $joiner = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        $invitation = $this->createInvitation($storyteller, $chronicleId);

        $this->actingAs($joiner, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept')
            ->assertOk();

        $response = $this->actingAs($joiner, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept');

        $response->assertStatus(409);
        $this->assertSame(
            ['chronicle_id', 'message'],
            array_keys($response->json()),
        );
        $response->assertJsonPath('chronicle_id', $chronicleId);
        $this->assertIsInt($response->json('chronicle_id'));
        $response->assertJsonPath('message', 'You are already a member of this chronicle.');
        $this->assertSame(1, ChronicleInvitation::query()->findOrFail($invitation['id'])->uses);
        $this->assertSame(1, ChronicleMember::query()->where('user_id', $joiner->id)->count());
    }

    public function test_accept_after_left_at_updates_the_same_membership(): void
    {
        $storyteller = User::factory()->create();
        $joiner = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        $invitation = $this->createInvitation($storyteller, $chronicleId);

        $this->actingAs($joiner, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept')
            ->assertOk();

        $member = ChronicleMember::query()
            ->where('chronicle_id', $chronicleId)
            ->where('user_id', $joiner->id)
            ->firstOrFail();
        $member->forceFill([
            'left_at' => now()->subDay(),
            'joined_at' => now()->subDays(2),
        ])->save();

        $response = $this->actingAs($joiner, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept');

        $response->assertOk();
        $response->assertJsonPath('chronicle_id', $chronicleId);
        $this->assertIsInt($response->json('chronicle_id'));

        $member->refresh();
        $this->assertNull($member->left_at);
        $this->assertTrue($member->joined_at->greaterThan(now()->subHour()));
        $this->assertSame(1, ChronicleMember::query()->where('user_id', $joiner->id)->count());
        $this->assertSame(2, ChronicleInvitation::query()->findOrFail($invitation['id'])->uses);
    }

    public function test_null_max_uses_does_not_limit_other_users(): void
    {
        $storyteller = User::factory()->create();
        $first = User::factory()->create();
        $second = User::factory()->create();
        $chronicleId = $this->createChronicle($storyteller);
        $invitation = $this->createInvitation($storyteller, $chronicleId, ['max_uses' => null]);

        $this->actingAs($first, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept')
            ->assertOk();
        $this->actingAs($second, 'sanctum')
            ->postJson('/api/invitations/'.$invitation['token'].'/accept')
            ->assertOk()
            ->assertJsonPath('chronicle_id', $chronicleId);

        $this->assertSame(2, ChronicleInvitation::query()->findOrFail($invitation['id'])->uses);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'role' => ChronicleRole::Player->value,
            'expires_at' => now()->addDay()->toIso8601String(),
            'max_uses' => 5,
        ], $overrides);
    }

    private function createChronicle(User $storyteller): int
    {
        $response = $this->actingAs($storyteller, 'sanctum')
            ->postJson('/api/chronicles', [
                'title' => 'Хроника приглашений',
            ]);

        $response->assertStatus(201);

        return (int) $response->json('chronicle.id');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array{id: int, token: string}
     */
    private function createInvitation(User $storyteller, int $chronicleId, array $overrides = []): array
    {
        $response = $this->actingAs($storyteller, 'sanctum')
            ->postJson("/api/chronicles/{$chronicleId}/invitations", $this->payload($overrides));

        $response->assertStatus(201);

        return [
            'id' => (int) $response->json('invitation.id'),
            'token' => (string) $response->json('invitation.token'),
        ];
    }
}
