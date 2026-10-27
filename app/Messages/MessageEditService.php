<?php

namespace App\Messages;

use App\Diary\DiaryScheduler;
use App\Models\Message;
use App\Models\User;

class MessageEditService
{
    public function __construct(private DiaryScheduler $diaryScheduler) {}

    public function toggleOoc(Message $message, User $user, bool $isOoc): Message
    {
        if ((bool) $message->is_ooc === $isOoc) {
            return $message;
        }

        $message->update(['is_ooc' => $isOoc]);

        $this->diaryScheduler->markStale($message, 'ooc_toggle');

        return $message->refresh();
    }

    public function softDelete(Message $message, User $user): void
    {
        if ($message->trashed()) {
            return;
        }

        $message->delete();

        $this->diaryScheduler->markStale($message, 'delete');
    }

    public function restore(Message $message, User $user): void
    {
        if (! $message->trashed()) {
            return;
        }

        $message->restore();

        $this->diaryScheduler->markStale($message, 'restore');
    }

    public function updateBody(Message $message, User $user, string $body): Message
    {
        $body = trim($body);
        if ($body === $message->body) {
            return $message;
        }

        $message->update(['body' => $body]);

        $this->diaryScheduler->markStale($message, 'edit');

        return $message->refresh();
    }
}
