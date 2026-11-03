<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS characters_one_active_player_per_chronicle');
    }

    public function down(): void
    {
        DB::statement(<<<'SQL'
CREATE UNIQUE INDEX characters_one_active_player_per_chronicle
ON characters (chronicle_id, user_id)
WHERE character_type = 'player' AND is_active = true
SQL);
    }
};
