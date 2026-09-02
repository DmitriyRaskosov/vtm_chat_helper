<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('context_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('level', 20);
            $table->unsignedBigInteger('first_message_id')->nullable();
            $table->unsignedBigInteger('last_message_id')->nullable();
            $table->text('content');
            $table->jsonb('metadata');
            $table->string('model', 120);
            $table->string('prompt_version', 80);
            $table->string('source_hash', 64)->unique();
            $table->timestamps();

            $table->index(['scene_id', 'level', 'first_message_id']);
            $table->index(['game_session_id', 'level']);
        });

        Schema::create('context_summary_sources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('context_summary_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 20);
            $table->unsignedBigInteger('source_id');
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['context_summary_id', 'source_type', 'source_id'], 'context_summary_source_unique');
            $table->unique(['context_summary_id', 'position']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('scene_context_states', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('last_summarized_message_id')->nullable();
            $table->unsignedBigInteger('last_l1_source_id')->nullable();
            $table->timestamps();
        });

        DB::statement(
            'INSERT INTO scene_context_states (scene_id, created_at, updated_at)
             SELECT id, NOW(), NOW() FROM scenes'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('scene_context_states');
        Schema::dropIfExists('context_summary_sources');
        Schema::dropIfExists('context_summaries');
    }
};
