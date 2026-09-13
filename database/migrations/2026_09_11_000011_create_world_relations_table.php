<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('world_relations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('chronicle_id');
            $table->unsignedBigInteger('source_entity_id');
            $table->unsignedBigInteger('target_entity_id');
            $table->foreignId('relation_type_id')->constrained('world_relation_types')->restrictOnDelete();
            $table->decimal('weight', 8, 4)->default(1);
            $table->text('note')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->jsonb('provenance')->nullable();
            $table->timestamps();

            $table->unique(['id', 'chronicle_id'], 'world_relations_id_chronicle_unique');
            $table->unique(['id', 'source_entity_id', 'target_entity_id'], 'world_relations_id_endpoints_unique');
            $table->index(['source_entity_id', 'relation_type_id'], 'world_relations_source_type_index');
            $table->index(['target_entity_id', 'relation_type_id'], 'world_relations_target_type_index');
        });

        DB::statement('ALTER TABLE world_relations ADD CONSTRAINT world_relations_not_self_check CHECK (source_entity_id <> target_entity_id)');

        Schema::table('world_relations', function (Blueprint $table) {
            $table->foreign(['source_entity_id', 'chronicle_id'], 'world_relations_source_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
            $table->foreign(['target_entity_id', 'chronicle_id'], 'world_relations_target_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_entities')
                ->restrictOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX world_relations_active_unique
             ON world_relations (chronicle_id, source_entity_id, target_entity_id, relation_type_id)
             WHERE ended_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('world_relations');
    }
};
