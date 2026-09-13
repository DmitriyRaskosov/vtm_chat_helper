<?php

namespace Tests\Feature;

use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Rag\EmbeddingProvider;
use App\Rag\RagIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use RuntimeException;
use Tests\TestCase;

class RagSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_cannot_search_rag(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/rag/search?q=элизиум')->assertForbidden();
    }

    public function test_posting_a_message_indexes_the_scoped_message_corpus(): void
    {
        $st = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->firstOrFail();

        Sanctum::actingAs($st);

        $this->postJson('/api/messages', ['body' => 'Каинит входит в Элизиум.'])
            ->assertCreated();

        $this->assertDatabaseHas('message_embeddings', [
            'content' => 'Каинит входит в Элизиум.',
        ]);

        $this->getJson(
            '/api/rag/search?q='.urlencode('Каинит входит в Элизиум.')
            .'&chronicle_id='.$scene->gameSession->chronicle_id,
        )
            ->assertOk()
            ->assertJsonPath('results.0.content', 'Каинит входит в Элизиум.')
            ->assertJsonPath('results.0.source_type', 'message');
    }

    public function test_search_requires_chronicle_scope(): void
    {
        $st = User::factory()->storyteller()->create();
        Sanctum::actingAs($st);

        $this->getJson('/api/rag/search?q=маскарад')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('chronicle_id');
    }

    public function test_message_index_is_idempotent_and_embedding_failure_leaves_canon(): void
    {
        $message = Message::factory()->create([
            'body' => 'Каинит входит в Элизиум.',
        ]);
        $indexer = $this->app->make(RagIndexer::class);
        $indexer->indexMessage($message);
        $indexer->indexMessage($message->fresh());

        $this->assertDatabaseCount('message_embeddings', 1);

        $this->app->instance(EmbeddingProvider::class, new class implements EmbeddingProvider
        {
            public function embed(string $text): array
            {
                throw new RuntimeException('embedding unavailable');
            }
        });

        $later = Message::factory()->create([
            'body' => 'Новая реплика не индексируется.',
        ]);

        try {
            $this->app->make(RagIndexer::class)->indexMessage($later);
            $this->fail('Expected embedding failure.');
        } catch (RuntimeException $e) {
            $this->assertSame('embedding unavailable', $e->getMessage());
        }

        $this->assertSame('Новая реплика не индексируется.', $later->fresh()->body);
        $this->assertDatabaseCount('message_embeddings', 1);
        $this->assertDatabaseHas('message_embeddings', [
            'message_id' => $message->id,
            'content' => 'Каинит входит в Элизиум.',
        ]);
    }
}
