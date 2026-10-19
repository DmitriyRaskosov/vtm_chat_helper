<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('character_states', function (Blueprint $table) {
            $table->unsignedBigInteger('character_id')->primary();
            $table->unsignedSmallInteger('blood_pool')->default(10);
            $table->unsignedSmallInteger('temporary_willpower')->default(5);
            $table->unsignedTinyInteger('health_level')->default(0);
            $table->string('health_damage', 16)->default('bashing');
            $table->smallInteger('health_penalty')->default(0);
            $table->timestamps();

            $table->foreign('character_id')
                ->references('id')
                ->on('characters')
                ->restrictOnDelete();
        });

        DB::statement("ALTER TABLE character_states ADD CONSTRAINT character_states_health_damage_check CHECK (health_damage IN ('bashing', 'lethal', 'aggravated'))");
        DB::statement('ALTER TABLE character_states ADD CONSTRAINT character_states_health_level_check CHECK (health_level <= 7)');
        DB::statement('ALTER TABLE character_states ADD CONSTRAINT character_states_blood_pool_check CHECK (blood_pool <= 50)');
        DB::statement('ALTER TABLE character_states ADD CONSTRAINT character_states_temporary_willpower_check CHECK (temporary_willpower <= 10)');
    }

    public function down(): void
    {
        Schema::dropIfExists('character_states');
    }
};
