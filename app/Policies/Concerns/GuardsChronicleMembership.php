<?php

namespace App\Policies\Concerns;

use App\Chronicle\Exceptions\ChronicleAccessDeniedException;
use App\Models\Chronicle;
use App\Models\ChronicleMember;
use App\Models\User;

trait GuardsChronicleMembership
{
    protected function ensureActiveMember(User $user, int $chronicleId): void
    {
        if (! $this->userIsActiveMember($user, $chronicleId)) {
            throw new ChronicleAccessDeniedException;
        }
    }

    protected function ensureActiveMemberOfChronicle(User $user, Chronicle $chronicle): void
    {
        $this->ensureActiveMember($user, (int) $chronicle->id);
    }

    private function userIsActiveMember(User $user, int $chronicleId): bool
    {
        return once(fn () => ChronicleMember::query()
            ->where('chronicle_id', $chronicleId)
            ->where('user_id', $user->id)
            ->whereNull('left_at')
            ->exists());
    }
}
