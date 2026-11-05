<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_nodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chronicle_location_id')
                ->constrained('chronicle_locations')
                ->cascadeOnDelete();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('location_nodes')
                ->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();

            $table->index('chronicle_location_id');
        });

        DB::statement(
            'ALTER TABLE location_nodes ADD COLUMN embedding vector(1024) NOT NULL'
        );

        DB::statement(
            'CREATE INDEX location_nodes_embedding_idx
             ON location_nodes USING hnsw (embedding vector_cosine_ops)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('location_nodes');
    }
};
