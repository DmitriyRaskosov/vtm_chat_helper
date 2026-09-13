<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lore_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lore_entry_version_id')->constrained('lore_entry_versions')->restrictOnDelete();
            $table->unsignedBigInteger('lore_entry_id');
            $table->unsignedBigInteger('chronicle_id');
            $table->unsignedInteger('chunk_index');
            $table->string('section', 32);
            $table->text('content');
            $table->unsignedInteger('token_estimate');
            $table->string('visibility', 32);
            $table->jsonb('metadata')->nullable();
            $table->vector('embedding', (int) config('rag.dimensions', 1024));
            $table->timestamps();

            $table->unique(['lore_entry_version_id', 'chunk_index'], 'lore_chunks_version_index_unique');
            $table->index(['chronicle_id', 'visibility'], 'lore_chunks_chronicle_visibility_index');
            $table->index('lore_entry_id', 'lore_chunks_lore_entry_id_index');
        });

        DB::statement("ALTER TABLE lore_chunks ADD CONSTRAINT lore_chunks_section_check CHECK (section IN ('title', 'canonical_text'))");
        DB::statement("ALTER TABLE lore_chunks ADD CONSTRAINT lore_chunks_visibility_check CHECK (visibility IN ('public', 'storyteller_only'))");
        DB::statement('ALTER TABLE lore_chunks ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector(\'simple\', content)) STORED');
        DB::statement('CREATE INDEX lore_chunks_embedding_hnsw ON lore_chunks USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX lore_chunks_search_vector_gin ON lore_chunks USING gin (search_vector)');

        Schema::table('lore_chunks', function (Blueprint $table) {
            $table->foreign(['lore_entry_id', 'chronicle_id'], 'lore_chunks_entry_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('lore_entries')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lore_chunks');
    }
};
