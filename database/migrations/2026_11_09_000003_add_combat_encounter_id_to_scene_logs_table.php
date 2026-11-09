<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scene_logs', function (Blueprint $table) {
            $table->foreignId('combat_encounter_id')
                ->nullable()
                ->after('scene_id')
                ->constrained('combat_encounters')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scene_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('combat_encounter_id');
        });
    }
};
