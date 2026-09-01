<?php

namespace App\Http\Controllers;

use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Http\Requests\StoreMessageRequest;
use App\Jobs\IndexRagMessageJob;
use App\Models\Message;
use App\Models\Scene;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scene_id' => ['sometimes', 'integer', 'exists:scenes,id'],
            'after_id' => ['sometimes', 'integer', 'min:0'],
        ]);
        $afterId = $request->integer('after_id');
        $scene = $this->resolveScene(
            isset($validated['scene_id']) ? (int) $validated['scene_id'] : null,
            false,
        );

        $messages = Message::query()
            ->with('user:id,name')
            ->where('scene_id', $scene->id)
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->get()
            ->map(fn (Message $message) => $this->serialize($message));

        return response()->json(['messages' => $messages]);
    }

    public function store(StoreMessageRequest $request): JsonResponse
    {
        $npcName = $request->validated('npc_name');
        $npcName = is_string($npcName) && $npcName !== '' ? $npcName : null;
        $sceneId = $request->validated('scene_id');
        $scene = $this->resolveScene($sceneId === null ? null : (int) $sceneId, true);

        $message = $request->user()->messages()->create([
            'scene_id' => $scene->id,
            'body' => $request->validated('body'),
            'npc_name' => $npcName,
        ]);

        $message->load('user:id,name');

        if (config('rag.index_sync')) {
            IndexRagMessageJob::dispatchSync($message->id);
        } else {
            IndexRagMessageJob::dispatch($message->id);
        }

        return response()->json(['message' => $this->serialize($message)], 201);
    }

    /**
     * @return array{id: int, scene_id: int, body: string, author: string, mine: bool, created_at: string, npc_name: string|null}
     */
    private function serialize(Message $message): array
    {
        $isNpc = $message->npc_name !== null && $message->npc_name !== '';

        return [
            'id' => $message->id,
            'scene_id' => $message->scene_id,
            'body' => $message->body,
            'author' => $message->displayAuthor(),
            'mine' => ! $isNpc && $message->user_id === auth()->id(),
            'npc_name' => $message->npc_name,
            'created_at' => $message->created_at?->timezone(config('app.timezone'))->format('H:i'),
        ];
    }

    private function resolveScene(?int $sceneId, bool $mustBeActive): Scene
    {
        $query = Scene::query()->whereHas(
            'gameSession',
            fn ($query) => $query->where('status', GameSessionStatus::Active),
        );

        if ($sceneId !== null) {
            $query->whereKey($sceneId);
        } else {
            $query->active();
        }

        $scene = $query->first();

        abort_if($scene === null, 409, 'There is no available scene in the active game session.');
        abort_if(
            $mustBeActive && $scene->status !== SceneStatus::Active,
            409,
            'Messages can only be posted to the active scene.',
        );

        return $scene;
    }
}
