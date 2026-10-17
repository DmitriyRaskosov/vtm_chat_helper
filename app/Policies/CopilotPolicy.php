<?php

namespace App\Policies;

use App\Models\CopilotRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CopilotPolicy
{
    public function use(User $user, CopilotRequest $copilotRequest): Response|bool
    {
        if ((int) $copilotRequest->storyteller_id === (int) $user->id) {
            return true;
        }

        return Response::deny('Copilot request belongs to another storyteller.');
    }
}
