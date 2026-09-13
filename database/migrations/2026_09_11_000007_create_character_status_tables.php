<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_status', function (Blueprint $table) {
            $table->unsignedBigInteger('character_id')->primary();
            $table->unsignedBigInteger('chronicle_id');
            $table->unsignedSmallInteger('temporary_willpower')->default(0);
            $table->unsignedSmallInteger('blood_pool')->default(0);
            $table->unsignedTinyInteger('hunger')->default(0);
            $table->string('health_state', 32)->default('healthy');
            $table->unsignedSmallInteger('fatigue')->default(0);
            $table->unsignedBigInteger('current_location_id')->nullable();
            $table->unsignedInteger('revision')->default(1);
            $table->timestamps();

            $table->unique(['character_id', 'chronicle_id'], 'character_status_character_chronicle_unique');
        });

        DB::statement("ALTER TABLE character_status ADD CONSTRAINT character_status_health_state_check CHECK (health_state IN ('healthy', 'bruised', 'injured', 'wounded', 'incapacitated', 'torpor'))");
        DB::statement('ALTER TABLE character_status ADD CONSTRAINT character_status_hunger_check CHECK (hunger <= 5)');

        Schema::table('character_status', function (Blueprint $table) {
            $table->foreign(['character_id', 'chronicle_id'], 'character_status_character_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('characters')
                ->restrictOnDelete();
            $table->foreign(['current_location_id', 'chronicle_id'], 'character_status_location_chronicle_foreign')
                ->references(['id', 'chronicle_id'])
                ->on('locations')
                ->restrictOnDelete();
        });

        Schema::create('character_status_effects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->restrictOnDelete();
            $table->string('effect_type', 32);
            $table->text('description');
            $table->jsonb('modifier')->nullable();
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_until')->nullable();
            $table->string('source_type', 32)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['character_id', 'is_active'], 'character_status_effects_character_active_index');
        });

        DB::statement("ALTER TABLE character_status_effects ADD CONSTRAINT character_status_effects_type_check CHECK (effect_type IN ('penalty', 'bonus', 'wound', 'temporary'))");

        Schema::create('character_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('character_id')->constrained('characters')->restrictOnDelete();
            $table->string('field', 64);
            $table->jsonb('old_value')->nullable();
            $table->jsonb('new_value')->nullable();
            $table->text('reason')->nullable();
            $table->foreignId('scene_id')->nullable()->constrained('scenes')->restrictOnDelete();
            $table->foreignId('message_id')->nullable()->constrained('messages')->restrictOnDelete();
            $table->string('game_time', 80)->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->unsignedInteger('revision');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['character_id', 'created_at'], 'character_status_changes_character_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('character_status_changes');
        Schema::dropIfExists('character_status_effects');
        Schema::dropIfExists('character_status');
    }
};
