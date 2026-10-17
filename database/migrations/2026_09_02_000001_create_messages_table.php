<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('copilot_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storyteller_id')->constrained('users')->restrictOnDelete();
            $table->string('npc_name', 64);
            $table->unsignedBigInteger('character_id')->nullable();
            $table->text('prompt');
            $table->jsonb('drafts');
            $table->jsonb('context_metadata');
            $table->string('model', 120);
            $table->string('prompt_version', 80);
            $table->string('builder_version', 80);
            $table->unsignedSmallInteger('selected_draft_index')->nullable();
            $table->timestamps();
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->string('npc_name', 64)->nullable();
            $table->unsignedBigInteger('author_character_id')->nullable();
            $table->foreignId('copilot_request_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->nullOnDelete();
            $table->unsignedInteger('token_estimate');
            $table->string('token_estimator_version', 32);
            $table->boolean('is_ooc')->default(false);
            $table->softDeletes();
            $table->timestamps();

            $table->index(['scene_id', 'is_ooc']);
        });

        DB::statement(
            'CREATE INDEX messages_scene_active_idx
            ON messages (scene_id)
            WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS messages_scene_active_idx');

        Schema::dropIfExists('messages');
        Schema::dropIfExists('copilot_requests');
    }
};
