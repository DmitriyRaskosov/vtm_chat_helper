<?php

namespace Tests\Support;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\ChronicleMember;
use App\Models\User;

trait JoinsChronicle
{
    protected function joinChronicle(User $user, Chronicle $chronicle, ChronicleRole $role): ChronicleMember
    {
        $factory = ChronicleMember::factory()
            ->for($chronicle)
            ->for($user);

        if ($role === ChronicleRole::Storyteller) {
            $factory = $factory->storyteller();
        } else {
            $factory = $factory->player();
        }

        return $factory->create();
    }

    protected function chronicleApi(Chronicle $chronicle, string $path): string
    {
        $path = ltrim($path, '/');

        return '/api/chronicles/'.$chronicle->id.'/'.$path;
    }
}
