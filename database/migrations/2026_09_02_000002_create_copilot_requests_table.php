<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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
            $table->text('prompt');
            $table->jsonb('drafts');
            $table->jsonb('context_metadata');
            $table->string('model', 120);
            $table->string('prompt_version', 80);
            $table->string('builder_version', 80);
            $table->unsignedSmallInteger('selected_draft_index')->nullable();
            $table->timestamps();
        });

        Schema::table('messages', function (Blueprint $table) {
            $table->foreignId('copilot_request_id')
                ->nullable()
                ->unique()
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('copilot_request_id');
        });

        Schema::dropIfExists('copilot_requests');
    }
};
