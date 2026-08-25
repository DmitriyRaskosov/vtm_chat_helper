<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS vector');

        Schema::create('rag_chunks', function (Blueprint $table) {
            $table->id();
            $table->string('source_type', 32);
            $table->string('source_id');
            $table->unsignedInteger('chunk_index')->default(0);
            $table->string('title')->nullable();
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->vector('embedding', (int) config('rag.dimensions', 768));
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'chunk_index']);
        });

        DB::statement('CREATE INDEX rag_chunks_embedding_hnsw ON rag_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('rag_chunks');
    }
};
