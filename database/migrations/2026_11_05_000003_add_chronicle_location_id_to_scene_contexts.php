<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scene_contexts', function (Blueprint $table) {
            $table->foreignId('chronicle_location_id')
                ->nullable()
                ->after('location_entity_id')
                ->constrained('chronicle_locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('scene_contexts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('chronicle_location_id');
        });
    }
};
