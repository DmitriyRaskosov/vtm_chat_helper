<?php

namespace App\Diary;

use App\Models\Message;
use Illuminate\Support\Facades\DB;

final class DiaryStaleMarker
{
    /**
     * Помечает L0/L1 записи дневника, диапазон которых покрывает сообщение.
     */
    public function markStaleCovering(Message $message): int
    {
        return DB::table('character_diary_entries')
            ->where('scene_id', $message->scene_id)
            ->whereIn('level', [0, 1])
            ->where('from_message_id', '<=', $message->id)
            ->where('to_message_id', '>=', $message->id)
            ->update([
                'is_stale' => true,
                'updated_at' => now(),
            ]);
    }
}
