<?php

namespace App\Http\Resources;

use App\Enums\CharacterType;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Заготовка под задачу 021; зритель передаётся явно, без auth().
 *
 * @mixin Message
 */
class MessageResource extends JsonResource
{
    /**
     * @param  array<int, array{name: string, type: CharacterType, playable: bool}>  $characterLookup
     */
    public function __construct(
        Message $resource,
        private readonly array $characterLookup,
        private readonly User $viewer,
    ) {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $message = $this->resource;
        $characterId = $message->author_character_id;
        $lookup = $characterId === null ? null : ($this->characterLookup[$characterId] ?? null);
        $isNpc = $lookup !== null
            ? $lookup['type'] === CharacterType::Npc
            : ($message->npc_name !== null && $message->npc_name !== '');

        $userName = $message->user?->name;
        $author = $message->is_ooc
            ? ($userName !== null && $userName !== '' ? $userName : 'Аноним')
            : $message->displayAuthor($lookup['name'] ?? null);

        return [
            'id' => $message->id,
            'scene_id' => $message->scene_id,
            'body' => $message->body,
            'author' => $author,
            'user_name' => $userName,
            'user_id' => (int) $message->user_id,
            'mine' => ! $message->is_system
                && ! $isNpc
                && (int) $message->user_id === (int) $this->viewer->id,
            'npc_name' => $message->npc_name,
            'author_character_id' => $characterId,
            'is_ooc' => (bool) $message->is_ooc,
            'is_system' => (bool) $message->is_system,
            'is_deleted' => $message->trashed(),
            'created_at' => $message->created_at?->timezone(config('app.timezone'))->format('H:i'),
        ];
    }
}
