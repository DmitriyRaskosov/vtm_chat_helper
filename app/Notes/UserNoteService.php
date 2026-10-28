<?php

namespace App\Notes;

use App\Models\User;
use App\Models\UserNote;

final class UserNoteService
{
    public function findForUser(User $user, ?int $sceneId): ?UserNote
    {
        return UserNote::query()
            ->where('user_id', $user->id)
            ->when(
                $sceneId === null,
                fn ($query) => $query->whereNull('scene_id'),
                fn ($query) => $query->where('scene_id', $sceneId),
            )
            ->first();
    }

    public function upsert(User $user, ?int $sceneId, string $content): UserNote
    {
        $note = $this->findForUser($user, $sceneId);

        if ($note === null) {
            return UserNote::query()->create([
                'user_id' => $user->id,
                'scene_id' => $sceneId,
                'content' => $content,
            ]);
        }

        $note->update(['content' => $content]);

        return $note->refresh();
    }
}
