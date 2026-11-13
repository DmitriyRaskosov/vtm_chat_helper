<?php

namespace App\Policies;

use App\Models\Chronicle;
use App\Models\LibraryCharacter;
use App\Models\User;
use App\Policies\Concerns\GuardsChronicleMembership;
use Illuminate\Auth\Access\Response;

class LibraryCharacterPolicy
{
    use GuardsChronicleMembership;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, LibraryCharacter $libraryCharacter): Response|bool
    {
        return $this->owns($user, $libraryCharacter);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, LibraryCharacter $libraryCharacter): Response|bool
    {
        return $this->owns($user, $libraryCharacter);
    }

    public function import(User $user, LibraryCharacter $libraryCharacter, Chronicle $chronicle): Response|bool
    {
        $owned = $this->owns($user, $libraryCharacter);
        if ($owned !== true) {
            return $owned;
        }

        $this->ensureActiveMemberOfChronicle($user, $chronicle);

        return true;
    }

    private function owns(User $user, LibraryCharacter $libraryCharacter): Response|bool
    {
        return (int) $libraryCharacter->user_id === (int) $user->id
            ? true
            : Response::deny('Шаблон недоступен.');
    }
}
