<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scene_logs', function (Blueprint $table) {
            $table->boolean('willpower_spent')->default(false)->after('comment');
        });
    }

    public function down(): void
    {
        Schema::table('scene_logs', function (Blueprint $table) {
            $table->dropColumn('willpower_spent');
        });
    }
};
