<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_diary_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')
                ->constrained('characters')
                ->cascadeOnDelete();
            $table->foreignId('chronicle_id')
                ->constrained('chronicles')
                ->cascadeOnDelete();
            $table->foreignId('scene_id')
                ->nullable()
                ->constrained('scenes')
                ->nullOnDelete();
            $table->smallInteger('level')->default(0);
            $table->unsignedBigInteger('from_message_id')->nullable();
            $table->unsignedBigInteger('to_message_id')->nullable();
            $table->text('entry');
            $table->timestamps();
            $table->boolean('is_stale')->default(false);

            $table->index(['character_id', 'created_at']);
            $table->index(['chronicle_id', 'level']);
            $table->index(['scene_id', 'level']);
        });

        DB::statement(
            'ALTER TABLE character_diary_entries ADD COLUMN embedding vector(1024) NOT NULL'
        );

        DB::statement(
            'CREATE INDEX character_diary_entries_embedding_idx
             ON character_diary_entries USING hnsw (embedding vector_cosine_ops)'
        );

        DB::statement(
            'CREATE INDEX character_diary_entries_stale_idx
            ON character_diary_entries (character_id)
            WHERE is_stale = true'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('character_diary_entries');
    }
};
