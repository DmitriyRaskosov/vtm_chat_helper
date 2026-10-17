<?php

namespace Tests\Support;

use App\Models\Message;
use App\Models\Scene;
use App\Models\User;

trait CreatesSceneMessages
{
    protected function createSceneMessage(
        string $body,
        bool $isOoc = false,
        ?Scene $scene = null,
        ?User $author = null,
    ): Message {
        /** @var Scene $scene */
        $scene ??= $this->scene;
        /** @var User $author */
        $author ??= $this->storyteller;

        return Message::query()->create([
            'user_id' => $author->id,
            'scene_id' => $scene->id,
            'body' => $body,
            'is_ooc' => $isOoc,
        ]);
    }
}
