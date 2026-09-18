<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_rule_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruleset_id')->constrained('rulesets')->restrictOnDelete();
            $table->string('edition', 64);
            $table->string('language', 16);
            $table->foreignId('rule_document_id')->constrained('rule_documents')->restrictOnDelete();
            $table->foreignId('rule_document_version_id')->nullable()->constrained('rule_document_versions')->restrictOnDelete();
            $table->foreignId('chronicle_rule_override_id')->nullable()->constrained('chronicle_rule_overrides')->restrictOnDelete();
            $table->unsignedBigInteger('chronicle_id')->nullable();
            $table->unsignedInteger('chunk_index');
            $table->string('section_path', 160);
            $table->text('content');
            $table->string('source_reference', 200)->nullable();
            $table->unsignedInteger('token_estimate');
            $table->jsonb('metadata')->nullable();
            $table->vector('embedding', (int) config('rag.dimensions', 1024));
            $table->timestamps();

            $table->index(['ruleset_id', 'edition'], 'game_rule_chunks_ruleset_edition_index');
            $table->index('rule_document_id', 'game_rule_chunks_document_index');
        });

        DB::statement(
            'ALTER TABLE game_rule_chunks ADD CONSTRAINT game_rule_chunks_source_check
             CHECK (
                (rule_document_version_id IS NOT NULL AND chronicle_rule_override_id IS NULL AND chronicle_id IS NULL)
                OR (rule_document_version_id IS NULL AND chronicle_rule_override_id IS NOT NULL AND chronicle_id IS NOT NULL)
             )'
        );
        DB::statement('ALTER TABLE game_rule_chunks ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector(\'simple\', content)) STORED');
        DB::statement('CREATE INDEX game_rule_chunks_embedding_hnsw ON game_rule_chunks USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX game_rule_chunks_search_vector_gin ON game_rule_chunks USING gin (search_vector)');

        Schema::table('game_rule_chunks', function (Blueprint $table) {
            $table->foreign('chronicle_id')->references('id')->on('chronicles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_rule_chunks');
    }
};
