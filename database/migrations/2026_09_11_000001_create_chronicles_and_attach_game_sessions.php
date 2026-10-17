<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE game_sessions ALTER COLUMN chronicle_id SET NOT NULL');

        DB::statement('DROP INDEX IF EXISTS game_sessions_single_active');
        DB::statement(
            "CREATE UNIQUE INDEX game_sessions_one_active_per_chronicle
             ON game_sessions (chronicle_id)
             WHERE status = 'active'"
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS game_sessions_one_active_per_chronicle');

        DB::statement('ALTER TABLE game_sessions ALTER COLUMN chronicle_id DROP NOT NULL');

        DB::statement(
            "CREATE UNIQUE INDEX game_sessions_single_active
             ON game_sessions (status)
             WHERE status = 'active'"
        );
    }
};
