<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('combat_encounters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('current_round')->default(1);
            $table->unsignedInteger('current_turn_index')->default(0);
            $table->jsonb('stat_keys')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        DB::statement(
            "CREATE UNIQUE INDEX combat_encounters_single_active_per_scene
             ON combat_encounters (scene_id)
             WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('combat_encounters');
    }
};
