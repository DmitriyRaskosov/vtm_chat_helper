<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMessageRequest;
use App\Http\Resources\MessageResource;
use App\Messages\MessageEditService;
use App\Messages\MessageService;
use App\Messages\PostMessageData;
use App\Models\Message;
use App\Scene\ActiveSceneResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ChatController extends Controller
{
    public function __construct(
        private ActiveSceneResolver $activeSceneResolver,
        private MessageService $messages,
        private MessageEditService $messageEdit,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scene_id' => ['sometimes', 'integer', 'exists:scenes,id'],
            'chronicle_id' => ['prohibited'],
            'after_id' => ['sometimes', 'integer', 'min:0'],
        ]);

        $user = $request->user();
        $afterId = $request->integer('after_id');
        $scene = $this->activeSceneResolver->resolve(
            isset($validated['scene_id']) ? (int) $validated['scene_id'] : null,
        );

        $messages = $this->messages->listForScene(
            $scene,
            $afterId,
            $request->boolean('include_deleted'),
        );

        $characterLookup = $this->messages->characterLookup($messages, $user);

        return response()->json([
            'messages' => $messages
                ->map(fn (Message $message) => (new MessageResource($message, $characterLookup, $user))->toArray($request))
                ->values(),
        ]);
    }

    public function store(StoreMessageRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $sceneId = $validated['scene_id'] ?? null;
        $scene = $this->activeSceneResolver->resolveActive(
            $sceneId === null ? null : (int) $sceneId,
        );
        $scene->loadMissing('gameSession');

        $user = $request->user();
        $data = new PostMessageData(
            body: $validated['body'],
            isOoc: (bool) ($validated['is_ooc'] ?? false),
            characterId: isset($validated['character_id']) ? (int) $validated['character_id'] : null,
            copilotRequestId: isset($validated['copilot_request_id']) ? (int) $validated['copilot_request_id'] : null,
            copilotDraftIndex: isset($validated['copilot_draft_index']) ? (int) $validated['copilot_draft_index'] : null,
        );

        $message = $this->messages->post($scene, $user, $data);
        $characterLookup = $this->messages->characterLookup(collect([$message]), $user);

        return response()->json([
            'message' => (new MessageResource($message, $characterLookup, $user))->toArray($request),
        ], 201);
    }

    public function toggleOoc(Request $request, Message $message): JsonResponse
    {
        $validated = $request->validate([
            'is_ooc' => ['required', 'boolean'],
        ]);

        $user = $request->user();
        $updated = $this->messageEdit->toggleOoc($message, $user, (bool) $validated['is_ooc']);
        $characterLookup = $this->messages->characterLookup(collect([$updated]), $user);

        return response()->json([
            'message' => (new MessageResource($updated, $characterLookup, $user))->toArray($request),
        ]);
    }

    public function destroy(Request $request, Message $message): Response
    {
        $this->messageEdit->softDelete($message, $request->user());

        return response()->noContent();
    }

    public function restore(Request $request, Message $message): JsonResponse
    {
        $user = $request->user();
        $this->messageEdit->restore($message, $user);

        $message->refresh();
        $characterLookup = $this->messages->characterLookup(collect([$message]), $user);

        return response()->json([
            'message' => (new MessageResource($message, $characterLookup, $user))->toArray($request),
        ]);
    }
}
