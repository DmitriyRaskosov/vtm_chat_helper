<?php

namespace App\Http\Controllers;

use App\Broadcasting\SafeBroadcaster;
use App\Events\UserTyping;
use App\Models\Scene;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SceneTypingController extends Controller
{
    public function store(Request $request, Scene $scene): Response
    {
        $this->authorize('view', $scene);

        $validated = $request->validate([
            'is_ooc' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();

        SafeBroadcaster::send(new UserTyping(
            (int) $scene->id,
            (int) $user->id,
            (string) ($user->name ?? ''),
            (bool) ($validated['is_ooc'] ?? false),
        ));

        return response()->noContent();
    }
}
