<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_relationships', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('chronicle_id');
            $table->unsignedBigInteger('source_character_id');
            $table->unsignedBigInteger('target_character_id');
            $table->timestamps();

            $table->unique(['id', 'chronicle_id'], 'character_relationships_id_chronicle_unique');
            $table->unique(['id', 'source_character_id', 'target_character_id'], 'character_relationships_id_endpoints_unique');
            $table->index(['source_character_id'], 'character_relationships_source_index');
            $table->index(['target_character_id'], 'character_relationships_target_index');
        });

        DB::statement('ALTER TABLE character_relationships ADD CONSTRAINT character_relationships_not_self_check CHECK (source_character_id <> target_character_id)');

        Schema::table('character_relationships', function (Blueprint $table) {
            $table->foreign(['id', 'chronicle_id'], 'character_relationships_relation_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('world_relations')
                ->restrictOnDelete();
            $table->foreign(['id', 'source_character_id', 'target_character_id'], 'character_relationships_relation_endpoints_foreign')
                ->references(['id', 'source_entity_id', 'target_entity_id'])
                ->on('world_relations')
                ->restrictOnDelete();
            $table->foreign(['source_character_id', 'chronicle_id'], 'character_relationships_source_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
            $table->foreign(['target_character_id', 'chronicle_id'], 'character_relationships_target_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_relationships');
    }
};
