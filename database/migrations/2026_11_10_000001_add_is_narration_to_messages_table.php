<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('messages', function (Blueprint $table) {
            $table->boolean('is_narration')->default(false);
        });

        DB::statement(
            'ALTER TABLE messages ADD CONSTRAINT messages_narration_exclusive_check CHECK (
                NOT (is_narration AND is_ooc)
                AND NOT (is_narration AND is_system)
            )'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE messages DROP CONSTRAINT IF EXISTS messages_narration_exclusive_check');

        Schema::table('messages', function (Blueprint $table) {
            $table->dropColumn('is_narration');
        });
    }
};
