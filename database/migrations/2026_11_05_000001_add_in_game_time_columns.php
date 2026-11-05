<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('chronicles', function (Blueprint $table) {
            $table->timestamp('current_in_game_date')->useCurrent();
        });

        Schema::table('scenes', function (Blueprint $table) {
            $table->timestamp('started_at_in_game')->nullable();
        });

        Schema::table('character_diary_entries', function (Blueprint $table) {
            $table->timestamp('in_game_date')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('character_diary_entries', function (Blueprint $table) {
            $table->dropColumn('in_game_date');
        });

        Schema::table('scenes', function (Blueprint $table) {
            $table->dropColumn('started_at_in_game');
        });

        Schema::table('chronicles', function (Blueprint $table) {
            $table->dropColumn('current_in_game_date');
        });
    }
};
