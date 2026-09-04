<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storyteller_intent_summaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storyteller_id')->constrained('users')->restrictOnDelete();
            $table->text('content');
            $table->unsignedBigInteger('first_copilot_request_id');
            $table->unsignedBigInteger('last_copilot_request_id');
            $table->unsignedInteger('request_count');
            $table->string('model', 120);
            $table->string('prompt_version', 80);
            $table->string('source_hash', 64)->unique();
            $table->timestamps();

            $table->index(['game_session_id', 'storyteller_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storyteller_intent_summaries');
    }
};
