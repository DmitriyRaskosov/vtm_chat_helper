<?php

namespace App\Policies;

use App\Models\Chronicle;
use App\Models\ChronicleMember;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    public function create(User $user, Scene $scene): Response|bool
    {
        $scene->loadMissing('gameSession.chronicle');
        $chronicle = $scene->gameSession->chronicle;

        return ChronicleMember::query()
            ->where('chronicle_id', $chronicle->id)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists()
            ? true
            : Response::deny('Вы не участник этой хроники.');
    }

    public function update(User $user, Message $message): Response|bool
    {
        return $this->denyUnlessCanEdit($user, $message);
    }

    public function delete(User $user, Message $message): Response|bool
    {
        return $this->denyUnlessCanEdit($user, $message);
    }

    public function restore(User $user, Message $message): Response|bool
    {
        return $this->denyUnlessCanEdit($user, $message);
    }

    private function denyUnlessCanEdit(User $user, Message $message): Response|bool
    {
        if ($user->isStorytellerIn($this->chronicleForMessage($message))) {
            return true;
        }

        if ((int) $message->user_id !== (int) $user->id) {
            return Response::deny('You cannot edit this message.');
        }

        $maxId = (int) Message::query()
            ->where('scene_id', $message->scene_id)
            ->max('id');

        if ($maxId <= 0 || (int) $message->id <= $maxId - 10) {
            return Response::deny('You cannot edit this message.');
        }

        return true;
    }

    private function chronicleForMessage(Message $message): Chronicle
    {
        $message->loadMissing('scene.gameSession.chronicle');

        return $message->scene->gameSession->chronicle;
    }
}
