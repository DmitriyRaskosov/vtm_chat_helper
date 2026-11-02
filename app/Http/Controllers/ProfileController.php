<?php

namespace App\Http\Controllers;

use App\Character\PlayerProfileService;
use App\Http\Resources\ProfileCharacterResource;
use App\Models\Character;
use App\Models\User;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProfileController extends Controller
{
    public function __construct(private PlayerProfileService $profiles) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewProfile', Character::class);

        $user = $request->user();
        if (! $user instanceof User) {
            throw new AuthenticationException;
        }

        return ProfileCharacterResource::collection(
            $this->profiles->charactersFor($user),
        );
    }
}
