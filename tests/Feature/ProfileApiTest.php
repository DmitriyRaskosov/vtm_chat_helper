<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\User;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use JoinsChronicle;

    public function test_player_sees_only_their_characters(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $chronicle = Chronicle::factory()->create();
        $this->joinChronicle($owner, $chronicle, ChronicleRole::Player);
        $this->joinChronicle($foreign, $chronicle, ChronicleRole::Player);

        $own = Character::factory()->player($owner)->forChronicle($chronicle)->create([
            'canonical_name' => 'Свой персонаж',
        ]);
        Character::factory()->player($foreign)->forChronicle($chronicle)->create([
            'canonical_name' => 'Чужой персонаж',
        ]);
        Character::factory()->npc()->forChronicle($chronicle)->create([
            'canonical_name' => 'НПС',
        ]);

        $this->actingAs($owner, 'sanctum')
            ->getJson('/api/profile/characters')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.canonical_name', 'Свой персонаж')
            ->assertJsonPath('data.0.chronicle_id', $chronicle->id)
            ->assertJsonPath('data.0.avatar_path', null);
    }

    public function test_player_sees_all_own_characters_in_profile(): void
    {
        $owner = User::factory()->create();
        $chronicle = Chronicle::factory()->create();
        $this->joinChronicle($owner, $chronicle, ChronicleRole::Player);

        $first = Character::factory()->player($owner)->forChronicle($chronicle)->create([
            'canonical_name' => 'Первый',
        ]);
        $second = Character::factory()->player($owner)->forChronicle($chronicle)->create([
            'canonical_name' => 'Второй',
        ]);

        $response = $this->actingAs($owner, 'sanctum')
            ->getJson('/api/profile/characters');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');
        $ids = collect($response->json('data'))->pluck('id')->sort()->values()->all();
        $this->assertSame([$first->id, $second->id], $ids);
    }

    public function test_foreign_player_with_no_characters_gets_empty_list(): void
    {
        $owner = User::factory()->create();
        $foreign = User::factory()->create();
        $chronicle = Chronicle::factory()->create();
        $this->joinChronicle($owner, $chronicle, ChronicleRole::Player);
        $this->joinChronicle($foreign, $chronicle, ChronicleRole::Player);

        Character::factory()->player($owner)->forChronicle($chronicle)->create([
            'canonical_name' => 'Только у владельца',
        ]);

        $this->actingAs($foreign, 'sanctum')
            ->getJson('/api/profile/characters')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_storyteller_sees_active_npcs_of_own_chronicles(): void
    {
        $storyteller = User::factory()->create();
        $chronicle = Chronicle::factory()->create(['created_by' => $storyteller->id]);
        $other = Chronicle::factory()->create();
        $this->joinChronicle($storyteller, $chronicle, ChronicleRole::Storyteller);

        $pc = Character::factory()->player($storyteller)->forChronicle($chronicle)->create([
            'canonical_name' => 'Свой PC',
        ]);
        $npc = Character::factory()->npc()->forChronicle($chronicle)->create([
            'canonical_name' => 'Свой NPC',
        ]);
        Character::factory()->npc()->forChronicle($chronicle)->create([
            'canonical_name' => 'Архивный NPC',
            'is_active' => false,
        ]);
        Character::factory()->npc()->forChronicle($other)->create([
            'canonical_name' => 'Чужой NPC',
        ]);

        $response = $this->actingAs($storyteller, 'sanctum')
            ->getJson('/api/profile/characters');

        $response->assertOk();
        $response->assertJsonCount(2, 'data');

        $rows = collect($response->json('data'))->keyBy('id');
        $this->assertSame('Свой PC', $rows[$pc->id]['canonical_name']);
        $this->assertSame('Свой NPC', $rows[$npc->id]['canonical_name']);
        $this->assertSame($chronicle->title, $rows[$pc->id]['chronicle_title']);
        $this->assertSame($chronicle->title, $rows[$npc->id]['chronicle_title']);
    }

    public function test_guest_is_unauthorized(): void
    {
        $this->getJson('/api/profile/characters')->assertUnauthorized();
    }
}
