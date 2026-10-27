<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserNote;
use Illuminate\Auth\Access\Response;

class UserNotePolicy
{
    public function view(User $user, UserNote $note): Response|bool
    {
        return (int) $note->user_id === (int) $user->id
            ? true
            : Response::deny('Заметка недоступна.');
    }

    public function update(User $user, ?UserNote $note = null): Response|bool
    {
        if ($note === null) {
            return true;
        }

        return (int) $note->user_id === (int) $user->id
            ? true
            : Response::deny('Заметка недоступна.');
    }
}
