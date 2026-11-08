<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('canon_lore_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('canon_lore_entry_id')
                ->constrained('canon_lore_entries')
                ->cascadeOnDelete();
            $table->string('header');
            $table->text('content');
            $table->unsignedInteger('token_estimate');
            $table->timestamps();

            $table->index('canon_lore_entry_id');
        });

        DB::statement(
            'ALTER TABLE canon_lore_chunks ADD COLUMN embedding vector(1024) NOT NULL'
        );

        DB::statement(
            'CREATE INDEX canon_lore_chunks_embedding_idx
             ON canon_lore_chunks USING hnsw (embedding vector_cosine_ops)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('canon_lore_chunks');
    }
};
