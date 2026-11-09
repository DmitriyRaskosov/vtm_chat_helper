<?php

use App\Models\ChronicleMember;
use App\Models\Scene;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::routes(['middleware' => ['auth:sanctum']]);

Broadcast::channel('chronicle.{chronicleId}', function (User $user, string $chronicleId): bool {
    return ChronicleMember::query()
        ->where('chronicle_id', (int) $chronicleId)
        ->where('user_id', $user->id)
        ->whereNull('left_at')
        ->exists();
});

Broadcast::channel('scene.{sceneId}', function (User $user, string $sceneId): bool {
    $scene = Scene::query()->with('gameSession')->find((int) $sceneId);

    if ($scene === null) {
        return false;
    }

    return ChronicleMember::query()
        ->where('chronicle_id', (int) $scene->gameSession->chronicle_id)
        ->where('user_id', $user->id)
        ->whereNull('left_at')
        ->exists();
});
