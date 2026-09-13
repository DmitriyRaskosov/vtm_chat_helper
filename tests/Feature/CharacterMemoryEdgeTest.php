<?php

namespace Tests\Feature;

use App\Enums\CharacterMemoryEdgeType;
use App\Enums\CharacterMemoryNodeType;
use App\Enums\WorldEntityType;
use App\Memory\CharacterMemoryException;
use App\Memory\CharacterMemoryService;
use App\Models\Character;
use App\Models\CharacterMemoryEdge;
use App\Models\Chronicle;
use App\World\WorldEntityService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CharacterMemoryEdgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_associate_creates_a_directed_edge_without_changing_authored_weight_on_read(): void
    {
        $character = $this->npc();
        $memory = $this->app->make(CharacterMemoryService::class);
        $opera = $memory->remember($character, 'Двор в опере.', CharacterMemoryNodeType::Place);
        $prince = $memory->remember($character, 'Князь на балконе.', CharacterMemoryNodeType::Person);

        $edge = $memory->associate(
            $opera,
            $prince,
            CharacterMemoryEdgeType::SamePlace,
            authoredWeight: 0.6,
            bidirectional: true,
        );

        $this->assertSame(0.6, $edge->authored_weight);
        $this->assertTrue($edge->bidirectional);
        $this->assertSame(0, $edge->traversal_count);

        $fromOpera = $memory->neighbors($opera);
        $fromPrince = $memory->neighbors($prince);

        $this->assertTrue($fromOpera->contains('id', $edge->id));
        $this->assertTrue($fromPrince->contains('id', $edge->id));
        $this->assertSame(0.6, $edge->fresh()->authored_weight);
        $this->assertSame(0, $edge->fresh()->traversal_count);
        $this->assertNull($edge->fresh()->last_traversed_at);
        $this->assertDatabaseCount('character_memory_edges', 1);
    }

    public function test_self_loop_and_cross_character_edges_are_rejected(): void
    {
        $alice = $this->npc('Виктория');
        $bob = $this->npc('Маркус');
        $memory = $this->app->make(CharacterMemoryService::class);
        $alicePlace = $memory->remember($alice, 'Опера Праги.', CharacterMemoryNodeType::Place);
        $bobPlace = $memory->remember($bob, 'Клуб Берлина.', CharacterMemoryNodeType::Place);

        try {
            $memory->associate($alicePlace, $alicePlace, CharacterMemoryEdgeType::SamePlace);
            $this->fail('Expected self-loop rejection.');
        } catch (CharacterMemoryException $e) {
            $this->assertSame('A memory edge cannot be a self-loop.', $e->getMessage());
        }

        $this->expectException(CharacterMemoryException::class);
        $memory->associate($alicePlace, $bobPlace, CharacterMemoryEdgeType::SamePlace);
    }

    public function test_duplicate_directed_and_bidirectional_reverse_edges_are_rejected(): void
    {
        $character = $this->npc();
        $memory = $this->app->make(CharacterMemoryService::class);
        $a = $memory->remember($character, 'Событие A.', CharacterMemoryNodeType::Event);
        $b = $memory->remember($character, 'Событие B.', CharacterMemoryNodeType::Event);
        $memory->associate($a, $b, CharacterMemoryEdgeType::Precedes, bidirectional: true);

        try {
            $memory->associate($a, $b, CharacterMemoryEdgeType::Precedes);
            $this->fail('Expected duplicate rejection.');
        } catch (CharacterMemoryException) {
        }

        $this->expectException(CharacterMemoryException::class);
        $memory->associate($b, $a, CharacterMemoryEdgeType::Precedes);
    }

    public function test_sql_self_loop_is_rejected(): void
    {
        $character = $this->npc();
        $node = $this->app->make(CharacterMemoryService::class)
            ->remember($character, 'Одно воспоминание.', CharacterMemoryNodeType::Rumor);

        $this->expectException(QueryException::class);
        DB::table('character_memory_edges')->insert([
            'character_id' => $character->id,
            'source_node_id' => $node->id,
            'target_node_id' => $node->id,
            'relation_type' => CharacterMemoryEdgeType::Reinforces->value,
            'authored_weight' => 1,
            'bidirectional' => false,
            'traversal_count' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_factory_creates_an_edge_for_one_character(): void
    {
        $edge = CharacterMemoryEdge::factory()->create();

        $this->assertSame($edge->source->character_id, $edge->target->character_id);
    }

    private function npc(string $name = 'Виктория'): Character
    {
        $chronicle = Chronicle::factory()->create();

        return Character::query()->findOrFail(
            $this->app->make(WorldEntityService::class)
                ->create($chronicle, WorldEntityType::Character, $name.'-'.uniqid())
                ->id,
        );
    }
}
