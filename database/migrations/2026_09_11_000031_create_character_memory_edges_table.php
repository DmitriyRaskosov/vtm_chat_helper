<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_memory_edges', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('character_id');
            $table->unsignedBigInteger('source_node_id');
            $table->unsignedBigInteger('target_node_id');
            $table->string('relation_type', 32);
            $table->decimal('authored_weight', 8, 4)->default(1);
            $table->boolean('bidirectional')->default(false);
            $table->unsignedInteger('traversal_count')->default(0);
            $table->timestamp('last_traversed_at')->nullable();
            $table->jsonb('provenance')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['source_node_id', 'relation_type'], 'character_memory_edges_source_type_index');
            $table->index(['target_node_id', 'relation_type'], 'character_memory_edges_target_type_index');
            $table->unique(
                ['character_id', 'source_node_id', 'target_node_id', 'relation_type'],
                'character_memory_edges_directed_unique',
            );
        });

        DB::statement("ALTER TABLE character_memory_edges ADD CONSTRAINT character_memory_edges_type_check CHECK (relation_type IN ('caused_recall', 'same_place', 'same_person', 'opponent', 'scent_association', 'emotional_echo', 'consequence', 'contradiction', 'reinforces', 'precedes'))");
        DB::statement('ALTER TABLE character_memory_edges ADD CONSTRAINT character_memory_edges_not_self_check CHECK (source_node_id <> target_node_id)');
        DB::statement('ALTER TABLE character_memory_edges ADD CONSTRAINT character_memory_edges_weight_check CHECK (authored_weight BETWEEN -1 AND 1)');

        Schema::table('character_memory_edges', function (Blueprint $table) {
            $table->foreign(['source_node_id', 'character_id'], 'character_memory_edges_source_character_foreign')
                ->references(['id', 'character_id'])
                ->on('character_memory_nodes')
                ->restrictOnDelete();
            $table->foreign(['target_node_id', 'character_id'], 'character_memory_edges_target_character_foreign')
                ->references(['id', 'character_id'])
                ->on('character_memory_nodes')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_memory_edges');
    }
};
