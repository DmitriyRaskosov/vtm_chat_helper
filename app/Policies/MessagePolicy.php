<?php

namespace App\Policies;

use App\Models\Chronicle;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Policies\Concerns\GuardsChronicleMembership;
use Illuminate\Auth\Access\Response;

class MessagePolicy
{
    use GuardsChronicleMembership;

    public function create(User $user, Scene $scene): bool
    {
        $scene->loadMissing('gameSession.chronicle');
        $this->ensureActiveMemberOfChronicle($user, $scene->gameSession->chronicle);

        return true;
    }

    public function narrate(User $user, Scene $scene): bool
    {
        $scene->loadMissing('gameSession.chronicle');

        return $user->isStorytellerIn($scene->gameSession->chronicle);
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
        $chronicle = $this->chronicleForMessage($message);
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

        if ($user->isStorytellerIn($chronicle)) {
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
