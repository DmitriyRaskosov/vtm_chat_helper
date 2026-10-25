<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scene_contexts', function (Blueprint $table) {
            $table->text('environment_details')->nullable()->after('storyteller_notes');
        });
    }

    public function down(): void
    {
        Schema::table('scene_contexts', function (Blueprint $table) {
            $table->dropColumn('environment_details');
        });
    }
};
