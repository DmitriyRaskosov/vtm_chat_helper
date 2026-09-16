<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('CREATE INDEX messages_scene_id_id_index ON messages (scene_id, id)');
        DB::statement('CREATE INDEX scenes_game_session_status_index ON scenes (game_session_id, status)');
        DB::statement(
            'CREATE INDEX world_relations_source_active_index
             ON world_relations (source_type, source_id)
             WHERE valid_to IS NULL'
        );
        DB::statement(
            'CREATE INDEX world_relations_target_active_index
             ON world_relations (target_type, target_id)
             WHERE valid_to IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS messages_scene_id_id_index');
        DB::statement('DROP INDEX IF EXISTS scenes_game_session_status_index');
        DB::statement('DROP INDEX IF EXISTS world_relations_source_active_index');
        DB::statement('DROP INDEX IF EXISTS world_relations_target_active_index');
    }
};
