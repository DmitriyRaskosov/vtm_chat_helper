<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->load([
            'chronicleMemberships' => static fn ($query) => $query->whereNull('left_at'),
            'chronicleMemberships.chronicle',
        ]);

        return response()->json([
            'user' => UserResource::make($user)->resolve(),
        ]);
    }
}
