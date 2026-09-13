<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_memory_nodes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('character_id');
            $table->text('node_text');
            $table->string('node_type', 32);
            $table->unsignedTinyInteger('importance')->default(1);
            $table->smallInteger('emotional_valence')->default(0);
            $table->unsignedTinyInteger('arousal')->default(0);
            $table->unsignedTinyInteger('confidence')->default(3);
            $table->boolean('is_false_belief')->default(false);
            $table->unsignedInteger('recall_count')->default(0);
            $table->timestamp('last_recalled_at')->nullable();
            $table->decimal('last_recall_score', 8, 4)->nullable();
            $table->string('status', 16)->default('draft');
            $table->jsonb('aliases')->default('[]');
            $table->jsonb('provenance')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->vector('embedding', (int) config('rag.dimensions', 1024));
            $table->timestamps();

            $table->unique(['id', 'character_id'], 'character_memory_nodes_id_character_unique');
            $table->index(['character_id', 'node_type'], 'character_memory_nodes_character_type_index');
        });

        DB::statement("ALTER TABLE character_memory_nodes ADD CONSTRAINT character_memory_nodes_type_check CHECK (node_type IN ('event', 'person', 'place', 'object', 'emotion', 'conclusion', 'promise', 'trauma', 'rumor'))");
        DB::statement("ALTER TABLE character_memory_nodes ADD CONSTRAINT character_memory_nodes_status_check CHECK (status IN ('draft', 'approved'))");
        DB::statement('ALTER TABLE character_memory_nodes ADD CONSTRAINT character_memory_nodes_importance_check CHECK (importance BETWEEN 0 AND 5)');
        DB::statement('ALTER TABLE character_memory_nodes ADD CONSTRAINT character_memory_nodes_valence_check CHECK (emotional_valence BETWEEN -5 AND 5)');
        DB::statement('ALTER TABLE character_memory_nodes ADD CONSTRAINT character_memory_nodes_arousal_check CHECK (arousal BETWEEN 0 AND 5)');
        DB::statement('ALTER TABLE character_memory_nodes ADD CONSTRAINT character_memory_nodes_confidence_check CHECK (confidence BETWEEN 0 AND 5)');
        DB::statement('ALTER TABLE character_memory_nodes ADD COLUMN search_vector tsvector GENERATED ALWAYS AS (to_tsvector(\'simple\', node_text)) STORED');
        DB::statement('CREATE INDEX character_memory_nodes_embedding_hnsw ON character_memory_nodes USING hnsw (embedding vector_cosine_ops)');
        DB::statement('CREATE INDEX character_memory_nodes_search_vector_gin ON character_memory_nodes USING gin (search_vector)');

        Schema::table('character_memory_nodes', function (Blueprint $table) {
            $table->foreign('character_id')->references('id')->on('characters')->restrictOnDelete();
        });

        Schema::table('character_lore_knowledge', function (Blueprint $table) {
            $table->unsignedBigInteger('source_memory_node_id')->nullable();
        });

        Schema::table('character_lore_knowledge', function (Blueprint $table) {
            $table->foreign(['source_memory_node_id', 'character_id'], 'character_lore_knowledge_memory_character_foreign')
                ->references(['id', 'character_id'])
                ->on('character_memory_nodes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('character_lore_knowledge', function (Blueprint $table) {
            $table->dropForeign('character_lore_knowledge_memory_character_foreign');
            $table->dropColumn('source_memory_node_id');
        });

        Schema::dropIfExists('character_memory_nodes');
    }
};
