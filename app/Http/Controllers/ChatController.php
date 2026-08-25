<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Jobs\IndexRagMessageJob;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $afterId = $request->integer('after_id');

        $messages = Message::query()
            ->with('user:id,name')
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

        $message = $request->user()->messages()->create([
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
     * @return array{id: int, body: string, author: string, mine: bool, created_at: string, npc_name: string|null}
     */
    private function serialize(Message $message): array
    {
        $isNpc = $message->npc_name !== null && $message->npc_name !== '';

        return [
            'id' => $message->id,
            'body' => $message->body,
            'author' => $message->displayAuthor(),
            'mine' => ! $isNpc && $message->user_id === auth()->id(),
            'npc_name' => $message->npc_name,
            'created_at' => $message->created_at?->timezone(config('app.timezone'))->format('H:i'),
        ];
    }
}
