<?php

namespace Tests\Feature;

use App\Enums\RagSourceType;
use App\Models\RagChunk;
use App\Models\User;
use App\Rag\RagIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RagSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_search_rag(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/rag/search?q=элизиум')->assertForbidden();
    }

    public function test_posting_a_message_indexes_a_chunk_and_storyteller_can_search(): void
    {
        $st = User::factory()->storyteller()->create();

        Sanctum::actingAs($st);

        $this->postJson('/api/messages', ['body' => 'Каинит входит в Элизиум.'])
            ->assertCreated();

        $this->assertDatabaseHas('rag_chunks', [
            'source_type' => RagSourceType::Message->value,
            'content' => 'Каинит входит в Элизиум.',
        ]);

        $this->getJson('/api/rag/search?q='.urlencode('Каинит входит в Элизиум.').'&types[]=message')
            ->assertOk()
            ->assertJsonPath('results.0.content', 'Каинит входит в Элизиум.')
            ->assertJsonPath('results.0.source_type', 'message');
    }

    public function test_lore_chunks_are_searchable(): void
    {
        $st = User::factory()->storyteller()->create();
        $this->app->make(RagIndexer::class)->indexLore(
            'masquerade',
            'Маскарад',
            'Каиниты не раскрывают свою природу смертным.',
        );

        Sanctum::actingAs($st);

        $this->getJson('/api/rag/search?q='.urlencode('Каиниты не раскрывают свою природу смертным.').'&types[]=lore')
            ->assertOk()
            ->assertJsonPath('results.0.source_type', 'lore')
            ->assertJsonPath('results.0.title', 'Маскарад');

        $this->assertSame(1, RagChunk::query()->where('source_type', RagSourceType::Lore)->count());
    }
}
