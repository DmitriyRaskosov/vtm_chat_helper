<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        $sessionId = DB::table('game_sessions')->insertGetId([
            'title' => 'Основная игровая сессия',
            'status' => 'active',
            'created_by' => null,
            'activated_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $sceneId = DB::table('scenes')->insertGetId([
            'game_session_id' => $sessionId,
            'position' => 1,
            'title' => 'Начальная сцена',
            'description' => null,
            'status' => 'active',
            'started_at' => $now,
            'ended_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('messages')
            ->whereNull('scene_id')
            ->update(['scene_id' => $sceneId]);
    }

    public function down(): void
    {
        // Data is intentionally retained. Structural rollback happens in later migrations.
    }
};
