<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        DB::statement(<<<'SQL'
            CREATE TABLE character_memories (
                id BIGSERIAL PRIMARY KEY,
                character_id BIGINT NOT NULL REFERENCES characters(id) ON DELETE CASCADE,
                chronicle_id BIGINT NOT NULL REFERENCES chronicles(id) ON DELETE CASCADE,
                type VARCHAR(32) NOT NULL,
                content TEXT NOT NULL,
                importance SMALLINT NOT NULL DEFAULT 5,
                involved_entity_ids BIGINT[] NOT NULL DEFAULT '{}',
                source_message_id BIGINT NULL REFERENCES messages(id) ON DELETE SET NULL,
                source_scene_id BIGINT NULL REFERENCES scenes(id) ON DELETE SET NULL,
                embedding vector(1024) NOT NULL,
                access_count INTEGER NOT NULL DEFAULT 0,
                last_accessed_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT NOW(),
                updated_at TIMESTAMP NOT NULL DEFAULT NOW()
            )
        SQL);

        DB::statement('CREATE INDEX character_memories_embedding_idx ON character_memories USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX character_memories_character_idx ON character_memories (character_id, created_at DESC)');
        DB::statement('CREATE INDEX character_memories_chronicle_idx ON character_memories (chronicle_id)');
        DB::statement('CREATE INDEX character_memories_type_idx ON character_memories (character_id, type)');
    }

    public function down(): void
    {
        Schema::dropIfExists('character_memories');
    }
};