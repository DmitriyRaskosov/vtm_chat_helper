<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
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
        $message = $request->user()->messages()->create([
            'body' => $request->validated('body'),
        ]);

        $message->load('user:id,name');

        return response()->json(['message' => $this->serialize($message)], 201);
    }

    /**
     * @return array{id: int, body: string, author: string, mine: bool, created_at: string}
     */
    private function serialize(Message $message): array
    {
        return [
            'id' => $message->id,
            'body' => $message->body,
            'author' => $message->user->name,
            'mine' => $message->user_id === auth()->id(),
            'created_at' => $message->created_at?->timezone(config('app.timezone'))->format('H:i'),
        ];
    }
}
