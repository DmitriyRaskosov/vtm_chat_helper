<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class DiceMacroApiTest extends TestCase
{
    public function test_guest_cannot_list_macros(): void
    {
        $this->getJson('/api/dice-macros')->assertUnauthorized();
    }

    public function test_user_creates_macro_and_lists_only_own(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/dice-macros', [
            'name' => 'Атака шпагой',
            'pool' => 6,
            'difficulty' => 6,
            'dice_type' => 10,
            'use_willpower' => true,
            'stats' => ['dexterity', 'athletics'],
        ]);

        $created->assertCreated();
        $created->assertJsonPath('macro.name', 'Атака шпагой');
        $created->assertJsonPath('macro.pool', 6);
        $created->assertJsonPath('macro.difficulty', 6);
        $created->assertJsonPath('macro.dice_type', 10);
        $created->assertJsonPath('macro.use_willpower', true);
        $created->assertJsonPath('macro.stats', ['dexterity', 'athletics']);
        $created->assertJsonPath('macro.user_id', $owner->id);

        $this->actingAs($other, 'sanctum')->postJson('/api/dice-macros', [
            'name' => 'Чужой бросок',
            'pool' => 3,
            'difficulty' => 7,
        ])->assertCreated();

        $list = $this->actingAs($owner, 'sanctum')->getJson('/api/dice-macros');
        $list->assertOk();
        $list->assertJsonCount(1, 'macros');
        $list->assertJsonPath('macros.0.name', 'Атака шпагой');
        $list->assertJsonPath('macros.0.stats', ['dexterity', 'athletics']);
    }

    public function test_user_updates_and_deletes_own_macro(): void
    {
        $owner = User::factory()->create();

        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/dice-macros', [
            'name' => 'Уклонение',
            'pool' => 4,
            'difficulty' => 6,
            'stats' => [],
        ]);
        $created->assertCreated();
        $id = (int) $created->json('macro.id');
        $this->assertSame([], $created->json('macro.stats'));
        $this->assertSame(10, $created->json('macro.dice_type'));
        $this->assertFalse($created->json('macro.use_willpower'));

        $updated = $this->actingAs($owner, 'sanctum')->patchJson("/api/dice-macros/{$id}", [
            'name' => 'Уклонение от выстрела',
            'pool' => 5,
        ]);
        $updated->assertOk();
        $updated->assertJsonPath('macro.name', 'Уклонение от выстрела');
        $updated->assertJsonPath('macro.pool', 5);
        $updated->assertJsonPath('macro.difficulty', 6);

        $this->actingAs($owner, 'sanctum')->deleteJson("/api/dice-macros/{$id}")
            ->assertOk()
            ->assertJsonPath('deleted', true);

        $this->actingAs($owner, 'sanctum')->getJson('/api/dice-macros')
            ->assertOk()
            ->assertJsonCount(0, 'macros');
    }

    public function test_user_cannot_update_or_delete_foreign_macro(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        $created = $this->actingAs($owner, 'sanctum')->postJson('/api/dice-macros', [
            'name' => 'Стандартная атака',
            'pool' => 5,
            'difficulty' => 6,
            'stats' => ['strength'],
        ]);
        $created->assertCreated();
        $id = (int) $created->json('macro.id');

        $this->actingAs($intruder, 'sanctum')->patchJson("/api/dice-macros/{$id}", [
            'name' => 'Подмена',
        ])->assertForbidden();

        $this->actingAs($intruder, 'sanctum')->deleteJson("/api/dice-macros/{$id}")
            ->assertForbidden();

        $this->actingAs($owner, 'sanctum')->getJson('/api/dice-macros')
            ->assertOk()
            ->assertJsonPath('macros.0.name', 'Стандартная атака')
            ->assertJsonPath('macros.0.stats', ['strength']);
    }
}
