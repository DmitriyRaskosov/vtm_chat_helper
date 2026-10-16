<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('character_diary_entries', function (Blueprint $table) {
            $table->decimal('importance', 4, 3)->nullable()->after('entry');
            $table->string('emotion', 32)->nullable()->after('importance');
        });
    }

    public function down(): void
    {
        Schema::table('character_diary_entries', function (Blueprint $table) {
            $table->dropColumn(['importance', 'emotion']);
        });
    }
};
