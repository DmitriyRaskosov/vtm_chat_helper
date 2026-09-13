<?php

namespace Tests\Feature;

use App\Enums\CharacterMemoryNodeType;
use App\Enums\WorldEntityType;
use App\Memory\CharacterMemorySearcher;
use App\Memory\CharacterMemoryService;
use App\Models\Character;
use App\Models\CharacterMemoryNode;
use App\Models\Chronicle;
use App\Models\User;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CharacterMemoryNodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_memory_nodes_have_no_timeline_column(): void
    {
        $this->assertTrue(Schema::hasTable('character_memory_nodes'));
        $this->assertFalse(Schema::hasColumn('character_memory_nodes', 'timeline_id'));
        $this->assertFalse(Schema::hasTable('chronicle_timeline'));
    }

    public function test_remember_stores_subjective_and_false_belief_nodes(): void
    {
        $character = $this->npc();
        $memory = $this->app->make(CharacterMemoryService::class);

        $true = $memory->remember(
            $character,
            'Виктория видела князя в опере.',
            CharacterMemoryNodeType::Event,
            importance: 4,
            confidence: 4,
            aliases: ['Пражский Элизиум'],
        );
        $false = $memory->remember(
            $character,
            'Князь уже мёртв.',
            CharacterMemoryNodeType::Conclusion,
            isFalseBelief: true,
            confidence: 2,
        );

        $this->assertFalse($true->is_false_belief);
        $this->assertTrue($false->is_false_belief);
        $this->assertSame(['Пражский Элизиум'], $true->aliases);
        $this->assertSame(0, $true->recall_count);
        $this->assertDatabaseCount('character_memory_nodes', 2);
    }

    public function test_search_is_scoped_to_character_and_does_not_update_recall(): void
    {
        $alice = $this->npc('Виктория');
        $bob = $this->npc('Маркус');
        $memory = $this->app->make(CharacterMemoryService::class);
        $aliceNode = $memory->remember($alice, 'Виктория видела князя в опере.', CharacterMemoryNodeType::Event);
        $memory->remember($bob, 'Маркус охотится в Берлине ночами.', CharacterMemoryNodeType::Event);

        $searcher = $this->app->make(CharacterMemorySearcher::class);
        $hits = $searcher->search($alice->id, 'Виктория видела князя в опере.');
        $leaked = $searcher->search($bob->id, 'Виктория видела князя в опере.');

        $this->assertNotEmpty($hits);
        $this->assertTrue($hits->every(fn (CharacterMemoryNode $node): bool => (int) $node->character_id === (int) $alice->id));
        $this->assertFalse($leaked->contains(fn (CharacterMemoryNode $node): bool => (int) $node->id === (int) $aliceNode->id));
        $this->assertSame(0, $aliceNode->fresh()->recall_count);
        $this->assertNull($aliceNode->fresh()->last_recalled_at);
    }

    public function test_exact_alias_matches_without_relying_on_vector_similarity(): void
    {
        $character = $this->npc();
        $this->app->make(CharacterMemoryService::class)->remember(
            $character,
            'Двор собирался под люстрой оперы.',
            CharacterMemoryNodeType::Place,
            aliases: ['Пражский Элизиум'],
        );

        $hits = $this->app->make(CharacterMemorySearcher::class)
            ->search($character->id, 'Пражский Элизиум');

        $this->assertNotEmpty($hits);
        $this->assertSame('Двор собирался под люстрой оперы.', $hits->first()->node_text);
    }

    public function test_posting_a_message_does_not_create_memory_nodes(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/messages', ['body' => 'Каинит входит в Элизиум.'])->assertCreated();

        $this->assertDatabaseCount('character_memory_nodes', 0);
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
