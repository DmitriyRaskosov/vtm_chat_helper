<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DIMENSIONS = 1024;

    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS rag_chunks_embedding_hnsw');
        DB::table('rag_chunks')->truncate();

        DB::statement('ALTER TABLE rag_chunks ALTER COLUMN embedding TYPE vector('.self::DIMENSIONS.')');
        DB::statement(
            'CREATE INDEX rag_chunks_embedding_hnsw ON rag_chunks USING hnsw (embedding vector_cosine_ops)',
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS rag_chunks_embedding_hnsw');
        DB::table('rag_chunks')->truncate();

        DB::statement('ALTER TABLE rag_chunks ALTER COLUMN embedding TYPE vector(768)');
        DB::statement(
            'CREATE INDEX rag_chunks_embedding_hnsw ON rag_chunks USING hnsw (embedding vector_cosine_ops)',
        );
    }
};
