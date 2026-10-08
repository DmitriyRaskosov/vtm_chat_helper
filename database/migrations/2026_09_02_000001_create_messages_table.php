<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('scene_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->string('npc_name', 64)->nullable();
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
        Schema::dropIfExists('messages');
    }
};
