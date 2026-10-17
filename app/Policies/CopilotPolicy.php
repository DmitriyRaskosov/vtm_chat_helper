<?php

namespace App\Policies;

use App\Models\CopilotRequest;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CopilotPolicy
{
    public function create(User $user, Scene $scene): Response|bool
    {
        return $user->isStoryteller()
            ? true
            : Response::deny('Только рассказчик.');
    }

    public function use(User $user, CopilotRequest $copilotRequest): Response|bool
    {
        if ((int) $copilotRequest->storyteller_id === (int) $user->id) {
            return true;
        }

        return Response::deny('Copilot request belongs to another storyteller.');
    }
}
