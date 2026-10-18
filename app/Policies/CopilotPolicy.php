<?php

namespace App\Policies;

use App\Models\Chronicle;
use App\Models\CopilotRequest;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class CopilotPolicy
{
    public function use(User $user, CopilotRequest $copilotRequest): Response|bool
    {
        if ((int) $copilotRequest->storyteller_id !== (int) $user->id) {
            return Response::deny('Copilot request belongs to another storyteller.');
        }

        if (! $user->isStorytellerIn($this->chronicleForRequest($copilotRequest))) {
            return Response::deny('Copilot request belongs to another storyteller.');
        }

        return true;
    }

    private function chronicleForRequest(CopilotRequest $copilotRequest): Chronicle
    {
        $copilotRequest->loadMissing('scene.gameSession.chronicle');

        return $copilotRequest->scene->gameSession->chronicle;
    }
}
