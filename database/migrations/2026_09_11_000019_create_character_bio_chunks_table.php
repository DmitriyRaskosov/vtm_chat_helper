<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_bio_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biography_version_id')->constrained('character_biography_versions')->restrictOnDelete();
            $table->foreignId('character_id')->constrained('characters')->restrictOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->string('section', 32);
            $table->text('content');
            $table->unsignedInteger('token_estimate');
            $table->jsonb('metadata')->nullable();
            $table->vector('embedding', (int) config('rag.dimensions', 1024));
            $table->timestamps();

            $table->unique(['biography_version_id', 'chunk_index'], 'character_bio_chunks_version_index_unique');
            $table->index('character_id', 'character_bio_chunks_character_id_index');
        });

        DB::statement("ALTER TABLE character_bio_chunks ADD CONSTRAINT character_bio_chunks_section_check CHECK (section IN ('summary', 'full_text', 'principles', 'motivation', 'fears', 'desires', 'behavioral_rules'))");
        DB::statement('CREATE INDEX character_bio_chunks_embedding_hnsw ON character_bio_chunks USING hnsw (embedding vector_cosine_ops)');
    }

    public function down(): void
    {
        Schema::dropIfExists('character_bio_chunks');
    }
};
