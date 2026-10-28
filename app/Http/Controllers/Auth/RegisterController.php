<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function store(RegisterRequest $request): JsonResponse
    {
        $user = User::query()->create(
            $request->safe()->only(['name', 'login', 'password']),
        );
        $user->load([
            'chronicleMemberships' => static fn ($query) => $query->whereNull('left_at'),
            'chronicleMemberships.chronicle',
        ]);

        return response()->json([
            'token' => $user->createToken('spa')->plainTextToken,
            'user' => UserResource::make($user)->resolve(),
        ], 201);
    }
}
