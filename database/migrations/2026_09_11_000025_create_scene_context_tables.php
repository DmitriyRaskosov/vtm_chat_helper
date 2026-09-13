<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scene_contexts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->unique()->constrained('scenes')->restrictOnDelete();
            $table->unsignedBigInteger('chronicle_id');
            $table->unsignedBigInteger('location_entity_id')->nullable();
            $table->text('atmosphere')->nullable();
            $table->text('situation')->nullable();
            $table->text('storyteller_notes')->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->unsignedInteger('frozen_revision')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['scene_id', 'chronicle_id'], 'scene_contexts_scene_chronicle_unique');
        });

        Schema::table('scene_contexts', function (Blueprint $table) {
            $table->foreign('chronicle_id', 'scene_contexts_chronicle_foreign')
                ->references('id')
                ->on('chronicles')
                ->restrictOnDelete();
            $table->foreign(['location_entity_id', 'chronicle_id'], 'scene_contexts_location_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('locations')
                ->restrictOnDelete();
        });

        Schema::create('scene_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scene_id')->constrained('scenes')->restrictOnDelete();
            $table->unsignedBigInteger('chronicle_id');
            $table->unsignedBigInteger('character_id');
            $table->string('role', 16);
            $table->boolean('visible')->default(true);
            $table->boolean('is_current')->default(true);
            $table->timestamp('entered_at');
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->index(['scene_id', 'is_current'], 'scene_participants_scene_current_index');
        });

        DB::statement("ALTER TABLE scene_participants ADD CONSTRAINT scene_participants_role_check CHECK (role IN ('npc', 'player', 'extra'))");

        Schema::table('scene_participants', function (Blueprint $table) {
            $table->foreign(['character_id', 'chronicle_id'], 'scene_participants_character_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
        });

        DB::statement(
            'CREATE UNIQUE INDEX scene_participants_current_unique
             ON scene_participants (scene_id, character_id)
             WHERE is_current'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('scene_participants');
        Schema::dropIfExists('scene_contexts');
    }
};
