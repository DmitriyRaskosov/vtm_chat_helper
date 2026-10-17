<?php

namespace App\Policies;

use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    public function create(User $user, Scene $scene): bool
    {
        return true;
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
        if ($user->isStoryteller()) {
            return true;
        }

        if ((int) $message->user_id === (int) $user->id) {
            return true;
        }

        return Response::deny('You cannot edit this message.');
    }
}
