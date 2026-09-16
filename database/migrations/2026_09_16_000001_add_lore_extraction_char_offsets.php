<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('extraction_runs', function (Blueprint $table) {
            $table->unsignedInteger('from_char_offset')->nullable()->after('to_message_id');
            $table->unsignedInteger('to_char_offset')->nullable()->after('from_char_offset');
        });
    }

    public function down(): void
    {
        Schema::table('extraction_runs', function (Blueprint $table) {
            $table->dropColumn(['from_char_offset', 'to_char_offset']);
        });
    }
};
