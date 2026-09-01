<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scenes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_session_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->string('title', 120);
            $table->text('description')->nullable();
            $table->string('status', 20)->default('draft');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['game_session_id', 'position']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX scenes_single_active_per_session
             ON scenes (game_session_id)
             WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('scenes');
    }
};
