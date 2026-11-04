<?php

namespace App\Policies;

use App\Models\DiceMacro;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class DiceMacroPolicy
{
    public function update(User $user, DiceMacro $macro): Response|bool
    {
        return $this->owns($user, $macro);
    }

    public function delete(User $user, DiceMacro $macro): Response|bool
    {
        return $this->owns($user, $macro);
    }

    private function owns(User $user, DiceMacro $macro): Response|bool
    {
        return (int) $macro->user_id === (int) $user->id
            ? true
            : Response::deny('Макрос недоступен.');
    }
}
