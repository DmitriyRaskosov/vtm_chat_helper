<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RetrievalGuardrailTest extends TestCase
{
    use RefreshDatabase;

    public function test_typical_lookup_indexes_exist(): void
    {
        $names = collect(DB::select(
            "SELECT indexname FROM pg_indexes WHERE schemaname = 'public'"
        ))->pluck('indexname');

        foreach ([
            'messages_scene_id_id_index',
            'scenes_game_session_status_index',
            'world_relations_source_active_index',
            'world_relations_target_active_index',
            'character_bio_chunks_search_vector_gin',
            'lore_chunks_embedding_hnsw',
            'lore_chunks_search_vector_gin',
            'game_rule_chunks_embedding_hnsw',
            'message_embeddings_embedding_hnsw',
            'character_status_effects_character_active_index',
        ] as $index) {
            $this->assertTrue($names->contains($index), "Missing index {$index}");
        }
    }

    public function test_graph_cte_keeps_limits_scope_and_statement_timeout(): void
    {
        $world = file_get_contents(app_path('World/WorldGraphRag.php'));
        $memory = file_get_contents(app_path('Memory/MemoryGraphRag.php'));
        $guard = file_get_contents(app_path('Retrieval/CteGuard.php'));

        $this->assertIsString($world);
        $this->assertIsString($memory);
        $this->assertIsString($guard);

        $this->assertStringContainsString('SET LOCAL statement_timeout', $guard);
        $this->assertStringContainsString('CteGuard::run', $world);
        $this->assertStringContainsString('CteGuard::run', $memory);
        $this->assertStringContainsString('LIMIT ?', $world);
        $this->assertStringContainsString('LIMIT ?', $memory);
        $this->assertStringContainsString('e.chronicle_id = ?', $world);
        $this->assertStringContainsString('n.character_id = ?', $memory);
        $this->assertStringContainsString('= ANY(w.path)', $world);
        $this->assertStringContainsString('= ANY(w.path)', $memory);
        $this->assertGreaterThan(0, (int) config('retrieval.statement_timeout_ms'));
    }
}
