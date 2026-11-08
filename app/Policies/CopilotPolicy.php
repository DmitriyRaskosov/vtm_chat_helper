<?php

namespace App\Policies;

use App\Models\Chronicle;
use App\Models\CopilotRequest;
use App\Models\User;
use App\Policies\Concerns\GuardsChronicleMembership;
use Illuminate\Auth\Access\Response;

class CopilotPolicy
{
    use GuardsChronicleMembership;

    public function use(User $user, CopilotRequest $copilotRequest): Response|bool
    {
        $chronicle = $this->chronicleForRequest($copilotRequest);
        $this->ensureActiveMemberOfChronicle($user, $chronicle);

        if ((int) $copilotRequest->storyteller_id !== (int) $user->id) {
            return Response::deny('Copilot request belongs to another storyteller.');
        }

        if (! $user->isStorytellerIn($chronicle)) {
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
