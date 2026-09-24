<?php

namespace App\Http\Controllers;

use App\Enums\CharacterType;
use App\Enums\GameSessionStatus;
use App\Enums\SceneStatus;
use App\Http\Requests\StoreMessageRequest;
use App\Extractor\SceneExtractionDispatcher;
use App\Models\Character;
use App\Models\Chronicle;
use App\Models\CopilotRequest;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'scene_id' => ['sometimes', 'integer', 'exists:scenes,id'],
            'chronicle_id' => ['sometimes', 'integer', 'exists:chronicles,id'],
            'after_id' => ['sometimes', 'integer', 'min:0'],
        ]);
        $afterId = $request->integer('after_id');
        $scene = $this->resolveScene(
            isset($validated['scene_id']) ? (int) $validated['scene_id'] : null,
            isset($validated['chronicle_id']) ? (int) $validated['chronicle_id'] : null,
            false,
        );

        $messages = Message::query()
            ->with('user:id,name')
            ->where('scene_id', $scene->id)
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
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
        $chronicleId = $validated['chronicle_id'] ?? null;
        $scene = $this->resolveScene(
            $sceneId === null ? null : (int) $sceneId,
            $chronicleId === null ? null : (int) $chronicleId,
            true,
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

        if (config('rag.index_sync')) {
            IndexRagMessageJob::dispatchSync($message->id);
        } else {
            IndexRagMessageJob::dispatch($message->id);
        }

        if ($request->user() !== null) {
            app(SceneExtractionDispatcher::class)->maybeDispatchAfterMessage(
                $scene->fresh(),
                $request->user(),
            );
        }

        return response()->json(['message' => $this->serialize($message, $characterLookup)], 201);
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
        abort_if(
            (int) $character->chronicle_id !== (int) $scene->gameSession->chronicle_id,
            409,
            'Character does not belong to this chronicle.',
        );
        abort_if(! $character->is_active, 409, 'Character is archived.');

        if ($this->isNpcLikeSpeech($character, $user)) {
            $snapshot = WorldEntity::query()
                ->whereKey($character->id)
                ->value('canonical_name');
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

        return $character->character_type === CharacterType::Ghoul
            && ! $character->isPlayableBy($user);
    }

    private function copilotMatchesMessage(
        CopilotRequest $copilotRequest,
        ?int $authorCharacterId,
    ): bool {
        return $copilotRequest->character_id !== null
            && (int) $copilotRequest->character_id === (int) $authorCharacterId;
    }

    /**
     * @return array{id: int, scene_id: int, body: string, author: string, mine: bool, created_at: string, npc_name: string|null, author_character_id: int|null}
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
            ->with('domitor:id,user_id')
            ->whereIn('id', $ids)
            ->get(['id', 'character_type', 'user_id', 'domitor_character_id'])
            ->keyBy('id');
        $names = WorldEntity::query()->whereIn('id', $ids)->pluck('canonical_name', 'id');
        $user = auth()->user();
        $lookup = [];

        foreach ($characters as $id => $character) {
            $lookup[(int) $id] = [
                'name' => (string) ($names[$id] ?? $names[(string) $id] ?? ''),
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

        return $lookup['type'] === CharacterType::Ghoul && ! $lookup['playable'];
    }

    private function resolveScene(?int $sceneId, ?int $chronicleId, bool $mustBeActive): Scene
    {
        if ($sceneId === null) {
            $chronicleId = Chronicle::resolveId($chronicleId);
        }

        $query = Scene::query()->whereHas(
            'gameSession',
            function ($query) use ($chronicleId) {
                $query->where('status', GameSessionStatus::Active);

                if ($chronicleId !== null) {
                    $query->where('chronicle_id', $chronicleId);
                }
            },
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
