<?php

namespace App\Http\Controllers;

use App\Character\CharacterSceneGuard;
use App\Enums\CharacterType;
use App\Http\Requests\StoreMessageRequest;
use App\Jobs\WriteDiaryJob;
use App\Messages\MessageEditService;
use App\Models\Character;
use App\Models\CopilotRequest;
use App\Models\Message;
use App\Models\Scene;
use App\Models\SceneParticipant;
use App\Models\User;
use App\Scene\ActiveSceneResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    public function __construct(
        private ActiveSceneResolver $activeSceneResolver,
        private CharacterSceneGuard $characterSceneGuard,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scene_id' => ['sometimes', 'integer', 'exists:scenes,id'],
            'chronicle_id' => ['prohibited'],
            'after_id' => ['sometimes', 'integer', 'min:0'],
        ]);

        $afterId = $request->integer('after_id');
        $scene = $this->activeSceneResolver->resolve(
            isset($validated['scene_id']) ? (int) $validated['scene_id'] : null,
        );

        $query = Message::query()
            ->with(['user:id,name', 'authorCharacter.entity'])
            ->where('scene_id', $scene->id);

        if ($request->boolean('include_deleted')) {
            $query->withTrashed();
        }

        $messages = $query
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->get();

        $characterLookup = $this->characterLookup($messages);

        return response()->json([
            'messages' => $messages
                ->map(fn (Message $message) => $this->serialize($message, $characterLookup))
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

        [$authorCharacterId, $npcName] = $this->resolveWorldAuthor($scene, $validated, $request->user());

        $message = DB::transaction(function () use ($request, $validated, $npcName, $authorCharacterId, $scene): Message {
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
                    || ! $this->copilotMatchesMessage($copilotRequest, $authorCharacterId),
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
                'author_character_id' => $authorCharacterId,
                'copilot_request_id' => $copilotRequest?->id,
                'is_ooc' => (bool) ($validated['is_ooc'] ?? false),
            ]);

            if ($copilotRequest !== null) {
                $copilotRequest->update([
                    'selected_draft_index' => (int) $validated['copilot_draft_index'],
                ]);
            }

            return $message;
        });

        $message->load('user:id,name');
        $characterLookup = $this->characterLookup(collect([$message]));

        $this->maybeDispatchDiaryWrite($scene);

        return response()->json(['message' => $this->serialize($message, $characterLookup)], 201);
    }

    public function toggleOoc(
        Request $request,
        Message $message,
        MessageEditService $service,
    ): JsonResponse {
        $validated = $request->validate([
            'is_ooc' => ['required', 'boolean'],
        ]);

        $updated = $service->toggleOoc($message, $request->user(), (bool) $validated['is_ooc']);

        $characterLookup = $this->characterLookup(collect([$updated]));

        return response()->json([
            'message' => $this->serialize($updated, $characterLookup),
        ]);
    }

    public function destroy(
        Message $message,
        MessageEditService $service,
    ): Response {
        $service->softDelete($message, request()->user());

        return response()->noContent();
    }

    public function restore(
        Message $message,
        MessageEditService $service,
    ): JsonResponse {
        $service->restore($message, request()->user());

        $message->refresh();
        $characterLookup = $this->characterLookup(collect([$message]));

        return response()->json([
            'message' => $this->serialize($message, $characterLookup),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: int|null, 1: string|null}
     */
    private function resolveWorldAuthor(Scene $scene, array $validated, ?User $user): array
    {
        $characterId = isset($validated['character_id']) ? (int) $validated['character_id'] : null;

        if ($characterId === null) {
            return [null, null];
        }

        $character = Character::query()->findOrFail($characterId);
        $this->characterSceneGuard->assertUsableIn($character, $scene);

        if ($this->isNpcLikeSpeech($character, $user)) {
            $character->loadMissing('entity');
            $snapshot = $character->entity?->canonical_name;
            $snapshot = is_string($snapshot) && $snapshot !== '' ? $snapshot : null;

            return [$character->id, $snapshot];
        }

        return [$character->id, null];
    }

    private function isNpcLikeSpeech(Character $character, ?User $user): bool
    {
        if ($character->character_type === CharacterType::Npc) {
            return true;
        }

        return false;
    }

    private function copilotMatchesMessage(
        CopilotRequest $copilotRequest,
        ?int $authorCharacterId,
    ): bool {
        return $copilotRequest->character_id !== null
            && (int) $copilotRequest->character_id === (int) $authorCharacterId;
    }

    /**
     * @return array{id: int, scene_id: int, body: string, author: string, mine: bool, created_at: string, npc_name: string|null, author_character_id: int|null, is_ooc: bool}
     */
    private function serialize(Message $message, array $characterLookup = []): array
    {
        $characterId = $message->author_character_id;
        $lookup = $characterId === null ? null : ($characterLookup[$characterId] ?? null);
        $isNpc = $lookup !== null
            ? $this->lookupIsNpcLike($lookup)
            : ($message->npc_name !== null && $message->npc_name !== '');

        return [
            'id' => $message->id,
            'scene_id' => $message->scene_id,
            'body' => $message->body,
            'author' => $message->displayAuthor($lookup['name'] ?? null),
            'mine' => ! $isNpc && $message->user_id === auth()->id(),
            'npc_name' => $message->npc_name,
            'author_character_id' => $characterId,
            'is_ooc' => (bool) $message->is_ooc,
            'is_deleted' => $message->trashed(),
            'created_at' => $message->created_at?->timezone(config('app.timezone'))->format('H:i'),
        ];
    }

    /**
     * @param  Collection<int, Message>  $messages
     * @return array<int, array{name: string, type: CharacterType, playable: bool}>
     */
    private function characterLookup(Collection $messages): array
    {
        $ids = $messages->pluck('author_character_id')->filter()->unique()->values()->all();

        if ($ids === []) {
            return [];
        }

        $characters = Character::query()
            ->whereIn('id', $ids)
            ->with('entity')
            ->get(['id', 'character_type', 'user_id'])
            ->keyBy('id');
        $user = auth()->user();
        $lookup = [];

        foreach ($characters as $id => $character) {
            $lookup[(int) $id] = [
                'name' => $character->entity === null
                    ? ''
                    : (string) $character->entity->canonical_name,
                'type' => $character->character_type,
                'playable' => $character->isPlayableBy($user instanceof User ? $user : null),
            ];
        }

        return $lookup;
    }

    /**
     * @param  array{name: string, type: CharacterType, playable: bool}  $lookup
     */
    private function lookupIsNpcLike(array $lookup): bool
    {
        if ($lookup['type'] === CharacterType::Npc) {
            return true;
        }

        return false;
    }

    private function maybeDispatchDiaryWrite(Scene $scene): void
    {
        $threshold = (int) config('diary.write_threshold', 15);
        if ($threshold <= 0) {
            return;
        }

        $participants = SceneParticipant::query()
            ->where('scene_id', $scene->id)
            ->where('is_current', true)
            ->get();

        if ($participants->isEmpty()) {
            return;
        }

        $minCursor = $participants->min(fn ($p) => (int) ($p->last_diary_message_id ?? 0));

        $pending = Message::query()
            ->where('scene_id', $scene->id)
            ->where('id', '>', $minCursor)
            ->count();

        if ($pending < $threshold) {
            return;
        }

        WriteDiaryJob::dispatch($scene->id);

        Log::info('diary.write.dispatched', [
            'scene_id' => $scene->id,
            'pending' => $pending,
            'threshold' => $threshold,
        ]);
    }
}
