<?php

namespace App\Messages;

use App\Models\CharacterDiaryEntry;
use App\Models\Message;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MessageEditService
{
    public function toggleOoc(Message $message, User $user, bool $isOoc): Message
    {
        $this->assertCanEdit($message, $user);

        if ((bool) $message->is_ooc === $isOoc) {
            return $message;
        }

        $message->update(['is_ooc' => $isOoc]);

        $this->markDiaryStale($message, 'ooc_toggle');

        return $message->refresh();
    }

    public function softDelete(Message $message, User $user): void
    {
        $this->assertCanEdit($message, $user);

        if ($message->trashed()) {
            return;
        }

        $message->delete();   // SoftDeletes проставит deleted_at

        $this->markDiaryStale($message, 'delete');
    }

    public function restore(Message $message, User $user): void
    {
        $this->assertCanEdit($message, $user);

        if (! $message->trashed()) {
            return;
        }

        $message->restore();

        $this->markDiaryStale($message, 'restore');
    }

    private function assertCanEdit(Message $message, User $user): void
    {
        if ($user->isStoryteller()) {
            return;
        }

        if ((int) $message->user_id === (int) $user->id) {
            return;
        }

        throw new AccessDeniedHttpException('You cannot edit this message.');
    }

    /**
     * Помечает все L0 и L1 записи дневника, в диапазон которых попало
     * это сообщение, как устаревшие. Мастер должен пересоздать их вручную.
     */
    private function markDiaryStale(Message $message, string $reason): void
    {
        if ($message->scene_id === null) {
            return;
        }

        $affected = DB::table('character_diary_entries')
            ->where('scene_id', $message->scene_id)
            ->whereIn('level', [0, 1])
            ->where('from_message_id', '<=', $message->id)
            ->where('to_message_id', '>=', $message->id)
            ->update([
                'is_stale' => true,
                'updated_at' => now(),
            ]);

        if ($affected > 0) {
            Log::info('diary.marked_stale', [
                'message_id' => $message->id,
                'scene_id' => $message->scene_id,
                'reason' => $reason,
                'affected' => $affected,
            ]);
        }
    }
}