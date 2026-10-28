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
            $table->string('source_type', 32);
            $table->unsignedBigInteger('source_id');
            $table->string('target_type', 32);
            $table->unsignedBigInteger('target_id');
            $table->string('relation', 64);
            $table->string('source_of_truth', 32)->default('chronicle');
            $table->unsignedTinyInteger('intensity')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->decimal('weight', 8, 4)->default(1);
            $table->text('note')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->jsonb('provenance')->nullable();
            $table->timestamps();

            $table->index(['chronicle_id']);
            $table->index(['source_type', 'source_id', 'relation'], 'world_relations_source_endpoint_index');
            $table->index(['target_type', 'target_id', 'relation'], 'world_relations_target_endpoint_index');
        });

        DB::statement(
            'ALTER TABLE world_relations ADD CONSTRAINT world_relations_not_self_check
             CHECK (NOT (source_type = target_type AND source_id = target_id))'
        );

        DB::statement(
            'CREATE UNIQUE INDEX world_relations_active_unique
             ON world_relations (chronicle_id, source_type, source_id, target_type, target_id, relation)
             WHERE valid_to IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('world_relations');
    }
};
