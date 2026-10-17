<?php

namespace App\Messages;

use App\Diary\DiaryScheduler;
use App\Models\Message;
use App\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MessageEditService
{
    public function __construct(private DiaryScheduler $diaryScheduler) {}

    public function toggleOoc(Message $message, User $user, bool $isOoc): Message
    {
        $this->assertCanEdit($message, $user);

        if ((bool) $message->is_ooc === $isOoc) {
            return $message;
        }

        $message->update(['is_ooc' => $isOoc]);

        $this->diaryScheduler->markStale($message, 'ooc_toggle');

        return $message->refresh();
    }

    public function softDelete(Message $message, User $user): void
    {
        $this->assertCanEdit($message, $user);

        if ($message->trashed()) {
            return;
        }

        $message->delete();   // SoftDeletes проставит deleted_at

        $this->diaryScheduler->markStale($message, 'delete');
    }

    public function restore(Message $message, User $user): void
    {
        $this->assertCanEdit($message, $user);

        if (! $message->trashed()) {
            return;
        }

        $message->restore();

        $this->diaryScheduler->markStale($message, 'restore');
    }

    private function assertCanEdit(Message $message, User $user): void
    {
        if ($user->isStoryteller()) {
            return;
        }

        if ((int) $message->user_id === (int) $user->id) {
            return;
        }

        // Авторизация: перенесётся в Policy (задача 030a).
        throw new AccessDeniedHttpException('You cannot edit this message.');
    }
}
