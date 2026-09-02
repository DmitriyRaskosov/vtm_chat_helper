<?php

namespace App\Http\Controllers;

use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Http\Requests\StoreMessageRequest;
use App\Jobs\IndexRagMessageJob;
use App\Jobs\SummarizeSceneWindowJob;
use App\Models\CopilotRequest;
use App\Models\Message;
use App\Models\Scene;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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
        $validated = $request->validated();
        $npcName = $validated['npc_name'] ?? null;
        $npcName = is_string($npcName) && $npcName !== '' ? $npcName : null;
        $sceneId = $validated['scene_id'] ?? null;
        $scene = $this->resolveScene($sceneId === null ? null : (int) $sceneId, true);

        $message = DB::transaction(function () use ($request, $validated, $npcName, $scene): Message {
            $copilotRequestId = $validated['copilot_request_id'] ?? null;
            $copilotRequest = null;

            if ($copilotRequestId !== null) {
                $copilotRequest = CopilotRequest::query()
                    ->lockForUpdate()
                    ->findOrFail((int) $copilotRequestId);

                abort_if($copilotRequest->message()->exists(), 409, 'Copilot request was already used.');
                abort_if(
                    $copilotRequest->storyteller_id !== $request->user()->id,
                    403,
                    'Copilot request belongs to another storyteller.',
                );
                abort_if(
                    $copilotRequest->scene_id !== $scene->id
                    || $copilotRequest->npc_name !== $npcName,
                    409,
                    'Copilot request does not match this message.',
                );

                $draftIndex = (int) $validated['copilot_draft_index'];
                abort_unless(
                    array_key_exists($draftIndex, $copilotRequest->drafts),
                    422,
                    'Selected Copilot draft does not exist.',
                );
            }

            $message = $request->user()->messages()->create([
                'scene_id' => $scene->id,
                'body' => $validated['body'],
                'npc_name' => $npcName,
                'copilot_request_id' => $copilotRequest?->id,
            ]);

            if ($copilotRequest !== null) {
                $copilotRequest->update([
                    'selected_draft_index' => (int) $validated['copilot_draft_index'],
                ]);
            }

            return $message;
        });

        $message->load('user:id,name');

        if (config('rag.index_sync')) {
            IndexRagMessageJob::dispatchSync($message->id);
        } else {
            IndexRagMessageJob::dispatch($message->id);
        }
        SummarizeSceneWindowJob::dispatch($scene->id);

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
