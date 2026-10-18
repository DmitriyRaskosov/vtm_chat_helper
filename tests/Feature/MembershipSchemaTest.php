<?php

namespace Tests\Feature;

use App\Enums\CharacterType;
use App\Enums\WorldEntityType;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\User;
use App\World\Exceptions\PlayerNotChronicleMemberException;
use App\World\WorldEntityService;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class MembershipSchemaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);
    }

    public function test_second_active_player_in_same_chronicle_fails_unique_index(): void
    {
        $chronicle = Chronicle::factory()->create();
        $user = User::factory()->create();

        Character::factory()->player($user)->forChronicle($chronicle)->create();

        $this->expectException(QueryException::class);

        Character::factory()->player($user)->forChronicle($chronicle)->create();
    }

    public function test_same_user_can_have_active_players_in_different_chronicles(): void
    {
        $user = User::factory()->create();
        $first = Chronicle::factory()->create();
        $second = Chronicle::factory()->create();

        $pcOne = Character::factory()->player($user)->forChronicle($first)->create();
        $pcTwo = Character::factory()->player($user)->forChronicle($second)->create();

        $this->assertNotSame($pcOne->chronicle_id, $pcTwo->chronicle_id);
        $this->assertSame($user->id, $pcOne->user_id);
        $this->assertSame($user->id, $pcTwo->user_id);
    }

    public function test_archived_player_does_not_block_new_active_player_in_chronicle(): void
    {
        $chronicle = Chronicle::factory()->create();
        $user = User::factory()->create();

        $archived = Character::factory()->player($user)->forChronicle($chronicle)->create([
            'is_active' => false,
        ]);

        $active = Character::factory()->player($user)->forChronicle($chronicle)->create();

        $this->assertFalse($archived->is_active);
        $this->assertTrue($active->is_active);
        $this->assertSame($chronicle->id, $active->chronicle_id);
    }

    public function test_player_without_chronicle_membership_rejected_by_service(): void
    {
        $chronicle = Chronicle::factory()->create();
        $user = User::factory()->create();

        $service = app(WorldEntityService::class);

        $this->expectException(PlayerNotChronicleMemberException::class);

        $service->create(
            $chronicle,
            WorldEntityType::Character,
            'Без членства',
            typed: [
                'character_type' => CharacterType::Player,
                'user_id' => $user->id,
            ],
        );
    }

    public function test_npc_with_null_user_id_inserts(): void
    {
        $character = Character::factory()->npc()->create();

        $this->assertDatabaseHas('characters', [
            'id' => $character->id,
            'character_type' => CharacterType::Npc->value,
            'user_id' => null,
        ]);
    }

    public function test_is_playable_by_and_policy_for_user_with_two_chronicles(): void
    {
        $user = User::factory()->create();
        $foreign = User::factory()->create();
        $chronicleA = Chronicle::factory()->create();
        $chronicleB = Chronicle::factory()->create();

        $ownA = Character::factory()->player($user)->forChronicle($chronicleA)->create();
        $ownB = Character::factory()->player($user)->forChronicle($chronicleB)->create();
        $foreignPc = Character::factory()->player($foreign)->forChronicle($chronicleA)->create();

        $this->assertTrue($ownA->isPlayableBy($user));
        $this->assertTrue($ownB->isPlayableBy($user));
        $this->assertFalse($foreignPc->isPlayableBy($user));
        $this->assertFalse($ownA->isPlayableBy($foreign));

        $this->assertTrue(Gate::forUser($user)->allows('speakAs', $ownA));
        $this->assertTrue(Gate::forUser($user)->allows('speakAs', $ownB));
        $this->assertFalse(Gate::forUser($user)->allows('speakAs', $foreignPc));

        $this->assertTrue(Gate::forUser($user)->allows('update', $ownA));
        $this->assertTrue(Gate::forUser($user)->allows('update', $ownB));
        $this->assertFalse(Gate::forUser($user)->allows('update', $foreignPc));
    }
}
