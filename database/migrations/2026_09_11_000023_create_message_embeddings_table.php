<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_sessions', function (Blueprint $table) {
            $table->unique(['id', 'chronicle_id'], 'game_sessions_id_chronicle_unique');
        });

        Schema::table('scenes', function (Blueprint $table) {
            $table->unique(['id', 'game_session_id'], 'scenes_id_session_unique');
        });

        Schema::create('message_embeddings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('message_id')->unique()->constrained('messages')->restrictOnDelete();
            $table->unsignedBigInteger('chronicle_id');
            $table->unsignedBigInteger('game_session_id');
            $table->unsignedBigInteger('scene_id');
            $table->text('content');
            $table->unsignedInteger('token_estimate');
            $table->vector('embedding', (int) config('rag.dimensions', 1024));
            $table->timestamps();

            $table->index(['chronicle_id', 'game_session_id'], 'message_embeddings_chronicle_session_index');
            $table->index('scene_id', 'message_embeddings_scene_id_index');
        });

        DB::statement("ALTER TABLE message_embeddings ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector('simple', content)) STORED");
        DB::statement('CREATE INDEX message_embeddings_embedding_hnsw ON message_embeddings USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX message_embeddings_search_vector_gin ON message_embeddings USING gin (search_vector)');

        Schema::table('message_embeddings', function (Blueprint $table) {
            $table->foreign(['game_session_id', 'chronicle_id'], 'message_embeddings_session_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('game_sessions')
                ->restrictOnDelete();
            $table->foreign(['scene_id', 'game_session_id'], 'message_embeddings_scene_session_foreign')
                ->references(['id', 'game_session_id'])
                ->on('scenes')
                ->restrictOnDelete();
        });

        DB::statement("
            INSERT INTO message_embeddings (
                message_id, chronicle_id, game_session_id, scene_id, content, token_estimate, embedding, created_at, updated_at
            )
            SELECT
                m.id,
                (r.metadata::jsonb->>'chronicle_id')::bigint,
                (r.metadata::jsonb->>'game_session_id')::bigint,
                (r.metadata::jsonb->>'scene_id')::bigint,
                r.content,
                COALESCE(m.token_estimate, 1),
                r.embedding,
                r.created_at,
                r.updated_at
            FROM rag_chunks r
            INNER JOIN messages m ON m.id = NULLIF(r.source_id, '')::bigint
            WHERE r.source_type = 'message'
              AND r.chunk_index = 0
              AND r.metadata IS NOT NULL
              AND (r.metadata::jsonb->>'chronicle_id') IS NOT NULL
              AND (r.metadata::jsonb->>'game_session_id') IS NOT NULL
              AND (r.metadata::jsonb->>'scene_id') IS NOT NULL
            ON CONFLICT (message_id) DO NOTHING
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('message_embeddings');

        Schema::table('scenes', function (Blueprint $table) {
            $table->dropUnique('scenes_id_session_unique');
        });

        Schema::table('game_sessions', function (Blueprint $table) {
            $table->dropUnique('game_sessions_id_chronicle_unique');
        });
    }
};
