<?php

namespace Tests\Feature;

use App\Models\Chronicle;
use App\Models\User;
use App\Models\WorldEntity;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorldEntityDeleteTest extends TestCase
{
    public function test_world_entity_cannot_be_deleted_from_database(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $chronicle = Chronicle::factory()->create([
            'title' => 'Тестовая хроника',
            'created_by' => $storyteller->id,
        ]);

        $response = $this->actingAs($storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Для удаления',
                'character_type' => 'npc',
                'chronicle_id' => $chronicle->id,
            ]);

        $response->assertStatus(201);
        $entityId = (int) $response->json('character.id');

        $this->assertNotNull(WorldEntity::query()->find($entityId));

        try {
            DB::table('world_entities')->where('id', $entityId)->delete();
            $this->fail('Expected PostgreSQL trigger to block world_entities delete.');
        } catch (QueryException $e) {
            $this->assertStringContainsString(
                'World entities cannot be deleted',
                $e->getMessage(),
            );
        }
    }
}
