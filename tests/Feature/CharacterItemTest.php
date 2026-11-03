<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Character;
use App\Models\CharacterItem;
use App\Models\Chronicle;
use App\Models\User;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class CharacterItemTest extends TestCase
{
    use JoinsChronicle;

    private User $owner;

    private User $foreign;

    private User $storyteller;

    private Chronicle $chronicle;

    private Character $ownCharacter;

    private Character $foreignCharacter;

    private Character $npc;

    protected function setUp(): void
    {
        parent::setUp();

        $this->storyteller = User::factory()->create();
        $this->owner = User::factory()->create();
        $this->foreign = User::factory()->create();

        $this->chronicle = Chronicle::factory()->create([
            'title' => 'Хроника инвентаря',
            'created_by' => $this->storyteller->id,
        ]);

        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->owner, $this->chronicle, ChronicleRole::Player);
        $this->joinChronicle($this->foreign, $this->chronicle, ChronicleRole::Player);

        $this->ownCharacter = Character::factory()
            ->forChronicle($this->chronicle)
            ->player($this->owner)
            ->create(['canonical_name' => 'Владелец']);

        $this->foreignCharacter = Character::factory()
            ->forChronicle($this->chronicle)
            ->player($this->foreign)
            ->create(['canonical_name' => 'Чужой']);

        $this->npc = Character::factory()
            ->forChronicle($this->chronicle)
            ->npc()
            ->create(['canonical_name' => 'NPC']);
    }

    public function test_owner_can_manage_own_inventory(): void
    {
        $create = $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/characters/{$this->ownCharacter->id}/items", [
                'name' => 'Трость',
                'quantity' => 1,
                'is_equipped' => true,
            ]);

        $create->assertStatus(201);
        $create->assertJsonPath('item.name', 'Трость');
        $create->assertJsonPath('item.quantity', 1);
        $create->assertJsonPath('item.is_equipped', true);
        $itemId = (int) $create->json('item.id');

        $this->assertDatabaseHas('character_items', [
            'id' => $itemId,
            'character_id' => $this->ownCharacter->id,
            'name' => 'Трость',
            'is_equipped' => true,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/characters/{$this->ownCharacter->id}/items/{$itemId}", [
                'name' => 'Трость с набалдашником',
                'is_equipped' => false,
                'quantity' => 2,
            ])
            ->assertStatus(200)
            ->assertJsonPath('item.name', 'Трость с набалдашником')
            ->assertJsonPath('item.is_equipped', false)
            ->assertJsonPath('item.quantity', 2);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/characters/{$this->ownCharacter->id}/items")
            ->assertStatus(200)
            ->assertJsonPath('items.0.name', 'Трость с набалдашником')
            ->assertJsonCount(1, 'items');

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/characters/{$this->ownCharacter->id}/items/{$itemId}")
            ->assertStatus(200)
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('character_items', ['id' => $itemId]);
    }

    public function test_player_cannot_change_another_players_inventory(): void
    {
        $this->actingAs($this->owner, 'sanctum')
            ->postJson("/api/characters/{$this->foreignCharacter->id}/items", [
                'name' => 'Чужой кинжал',
            ])
            ->assertStatus(403);

        $this->assertDatabaseMissing('character_items', [
            'character_id' => $this->foreignCharacter->id,
            'name' => 'Чужой кинжал',
        ]);

        $item = CharacterItem::query()->create([
            'character_id' => $this->foreignCharacter->id,
            'name' => 'Плащ',
            'quantity' => 1,
            'is_equipped' => false,
        ]);

        $this->actingAs($this->owner, 'sanctum')
            ->getJson("/api/characters/{$this->foreignCharacter->id}/items")
            ->assertStatus(403);

        $this->actingAs($this->owner, 'sanctum')
            ->patchJson("/api/characters/{$this->foreignCharacter->id}/items/{$item->id}", [
                'name' => 'Подмена',
            ])
            ->assertStatus(403);

        $this->actingAs($this->owner, 'sanctum')
            ->deleteJson("/api/characters/{$this->foreignCharacter->id}/items/{$item->id}")
            ->assertStatus(403);

        $this->assertDatabaseHas('character_items', [
            'id' => $item->id,
            'name' => 'Плащ',
        ]);
    }

    public function test_storyteller_can_manage_npc_inventory(): void
    {
        $create = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson("/api/characters/{$this->npc->id}/items", [
                'name' => 'Ключ',
                'description' => 'От склепа',
            ]);

        $create->assertStatus(201);
        $itemId = (int) $create->json('item.id');

        $this->actingAs($this->storyteller, 'sanctum')
            ->getJson("/api/characters/{$this->npc->id}/items")
            ->assertStatus(200)
            ->assertJsonPath('items.0.name', 'Ключ')
            ->assertJsonPath('items.0.description', 'От склепа');

        $this->actingAs($this->storyteller, 'sanctum')
            ->deleteJson("/api/characters/{$this->npc->id}/items/{$itemId}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('character_items', ['id' => $itemId]);
    }

    public function test_guest_cannot_list_items(): void
    {
        $this->getJson("/api/characters/{$this->ownCharacter->id}/items")
            ->assertStatus(401);
    }
}
