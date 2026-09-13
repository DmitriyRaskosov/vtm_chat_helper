<?php

namespace Tests\Feature;

use App\Enums\CharacterMemoryEdgeType;
use App\Enums\CharacterMemoryNodeType;
use App\Enums\WorldEntityType;
use App\Memory\CharacterMemoryService;
use App\Memory\MemoryGraphRag;
use App\Models\Character;
use App\Models\CharacterMemoryEdge;
use App\Models\Chronicle;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class MemoryGraphRagTest extends TestCase
{
    use RefreshDatabase;

    public function test_depth_two_does_not_include_a_fourth_hop(): void
    {
        $character = $this->npc();
        $memory = $this->app->make(CharacterMemoryService::class);
        $a = $memory->remember($character, 'Узел A опера.', CharacterMemoryNodeType::Place);
        $b = $memory->remember($character, 'Узел B князь.', CharacterMemoryNodeType::Person);
        $c = $memory->remember($character, 'Узел C кровь.', CharacterMemoryNodeType::Event);
        $d = $memory->remember($character, 'Узел D рассвет.', CharacterMemoryNodeType::Conclusion);
        $memory->associate($a, $b, CharacterMemoryEdgeType::Precedes, authoredWeight: 0.9);
        $memory->associate($b, $c, CharacterMemoryEdgeType::Precedes, authoredWeight: 0.9);
        $memory->associate($c, $d, CharacterMemoryEdgeType::Precedes, authoredWeight: 0.9);

        $bundle = $this->app->make(MemoryGraphRag::class)->expandSeeds($character, new Collection([$a]));
        $ids = collect($bundle->nodes)->pluck('id')->all();

        $this->assertContains($a->id, $ids);
        $this->assertContains($b->id, $ids);
        $this->assertContains($c->id, $ids);
        $this->assertNotContains($d->id, $ids);
        $this->assertTrue(collect($bundle->nodes)->firstWhere('id', $a->id)?->seed);
        $this->assertSame([$a->id], $bundle->seedIds);
    }

    public function test_cycles_do_not_duplicate_nodes_or_grow_unbounded(): void
    {
        $character = $this->npc();
        $memory = $this->app->make(CharacterMemoryService::class);
        $a = $memory->remember($character, 'Цикл A.', CharacterMemoryNodeType::Event);
        $b = $memory->remember($character, 'Цикл B.', CharacterMemoryNodeType::Event);
        $c = $memory->remember($character, 'Цикл C.', CharacterMemoryNodeType::Event);
        $memory->associate($a, $b, CharacterMemoryEdgeType::Consequence, authoredWeight: 0.8);
        $memory->associate($b, $c, CharacterMemoryEdgeType::Consequence, authoredWeight: 0.8);
        $memory->associate($c, $a, CharacterMemoryEdgeType::Consequence, authoredWeight: 0.8);

        $bundle = $this->app->make(MemoryGraphRag::class)->expandSeeds($character, new Collection([$a]));
        $ids = collect($bundle->nodes)->pluck('id');

        $this->assertCount(3, $ids->unique());
        $this->assertEqualsCanonicalizing([$a->id, $b->id, $c->id], $ids->all());
        $this->assertLessThanOrEqual(3, count($bundle->edges));
    }

    public function test_weak_edges_are_not_traversed(): void
    {
        $character = $this->npc();
        $memory = $this->app->make(CharacterMemoryService::class);
        $opera = $memory->remember($character, 'Двор в опере.', CharacterMemoryNodeType::Place);
        $whisper = $memory->remember($character, 'Слабый слух о Шабаше.', CharacterMemoryNodeType::Rumor);
        $memory->associate($opera, $whisper, CharacterMemoryEdgeType::ScentAssociation, authoredWeight: 0.05);

        $bundle = $this->app->make(MemoryGraphRag::class)->expandSeeds($character, new Collection([$opera]));

        $this->assertSame([$opera->id], collect($bundle->nodes)->pluck('id')->all());
        $this->assertSame([], $bundle->edges);
        $this->assertSame(0, $opera->fresh()->recall_count);
        $this->assertSame(0, CharacterMemoryEdge::query()->firstOrFail()->traversal_count);
    }

    public function test_another_character_graph_is_isolated(): void
    {
        $alice = $this->npc('Виктория');
        $bob = $this->npc('Маркус');
        $memory = $this->app->make(CharacterMemoryService::class);
        $alicePlace = $memory->remember($alice, 'Опера Праги.', CharacterMemoryNodeType::Place);
        $alicePerson = $memory->remember($alice, 'Князь на балконе.', CharacterMemoryNodeType::Person);
        $bobPlace = $memory->remember($bob, 'Клуб Берлина.', CharacterMemoryNodeType::Place);
        $bobPerson = $memory->remember($bob, 'Анархи в подвале.', CharacterMemoryNodeType::Person);
        $memory->associate($alicePlace, $alicePerson, CharacterMemoryEdgeType::SamePlace, authoredWeight: 0.7);
        $memory->associate($bobPlace, $bobPerson, CharacterMemoryEdgeType::SamePlace, authoredWeight: 0.7);

        $bundle = $this->app->make(MemoryGraphRag::class)->expandSeeds($alice, new Collection([$alicePlace]));
        $ids = collect($bundle->nodes)->pluck('id')->all();

        $this->assertContains($alicePlace->id, $ids);
        $this->assertContains($alicePerson->id, $ids);
        $this->assertNotContains($bobPlace->id, $ids);
        $this->assertNotContains($bobPerson->id, $ids);
        $this->assertTrue(collect($bundle->edges)->every(
            fn ($edge): bool => in_array($edge->sourceNodeId, $ids, true) && in_array($edge->targetNodeId, $ids, true),
        ));
    }

    public function test_result_is_bounded_and_stable(): void
    {
        config()->set('retrieval.memory_graphrag.max_nodes', 3);
        config()->set('retrieval.memory_graphrag.max_edges', 2);
        $character = $this->npc();
        $memory = $this->app->make(CharacterMemoryService::class);
        $seed = $memory->remember($character, 'Центр воспоминания.', CharacterMemoryNodeType::Event);
        foreach (range(1, 6) as $n) {
            $neighbor = $memory->remember($character, "Сосед {$n} Элизиума.", CharacterMemoryNodeType::Person);
            $memory->associate($seed, $neighbor, CharacterMemoryEdgeType::SamePerson, authoredWeight: 0.5 + ($n / 20));
        }

        $first = $this->app->make(MemoryGraphRag::class)->expandSeeds($character, new Collection([$seed]));
        $second = $this->app->make(MemoryGraphRag::class)->expandSeeds($character, new Collection([$seed]));

        $this->assertLessThanOrEqual(3, count($first->nodes));
        $this->assertLessThanOrEqual(2, count($first->edges));
        $this->assertSame(
            collect($first->nodes)->pluck('id')->all(),
            collect($second->nodes)->pluck('id')->all(),
        );
        $this->assertTrue(collect($first->nodes)->every(fn ($node): bool => $node->id !== 0 && $node->text !== ''));
        $this->assertTrue(collect($first->edges)->every(fn ($edge): bool => $edge->id !== 0));
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
