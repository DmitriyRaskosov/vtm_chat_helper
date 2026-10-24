<?php

namespace App\Messages;

use App\Diary\DiaryScheduler;
use App\Enums\CharacterType;
use App\Messages\Exceptions\CopilotDraftNotFoundException;
use App\Messages\Exceptions\CopilotRequestAlreadyUsedException;
use App\Messages\Exceptions\CopilotRequestMismatchException;
use App\Models\Character;
use App\Models\CopilotRequest;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\World\MixedChronicleException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class MessageService
{
    public function __construct(private DiaryScheduler $diaryScheduler) {}

    /**
     * @return Collection<int, Message>
     */
    public function listForScene(Scene $scene, int $afterId, bool $includeDeleted): Collection
    {
        $query = Message::query()
            ->with(['user:id,name', 'authorCharacter.entity'])
            ->where('scene_id', $scene->id);

        if ($includeDeleted) {
            $query->withTrashed();
        }

        return $query
            ->when($afterId > 0, fn ($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{0: int|null, 1: string|null}
     */
    public function resolveWorldAuthor(Scene $scene, User $user, ?int $characterId): array
    {
        if ($characterId === null) {
            return [null, null];
        }

        $character = Character::query()->findOrFail($characterId);
        $scene->loadMissing('gameSession');

        if ((int) $character->chronicle_id !== (int) $scene->gameSession->chronicle_id) {
            throw new MixedChronicleException;
        }

        if ($character->character_type === CharacterType::Npc) {
            $character->loadMissing('entity');
            $snapshot = $character->entity?->canonical_name;
            $snapshot = is_string($snapshot) && $snapshot !== '' ? $snapshot : null;

            return [$character->id, $snapshot];
        }

        return [$character->id, null];
    }

    public function post(Scene $scene, User $user, PostMessageData $data): Message
    {
        [$authorCharacterId, $npcName] = $this->resolveWorldAuthor($scene, $user, $data->characterId);

        $message = DB::transaction(function () use ($scene, $user, $data, $authorCharacterId, $npcName): Message {
            if ($data->copilotRequestId === null) {
                return $user->messages()->create([
                    'scene_id' => $scene->id,
                    'body' => $data->body,
                    'npc_name' => $npcName,
                    'author_character_id' => $authorCharacterId,
                    'copilot_request_id' => null,
                    'is_ooc' => $data->isOoc,
                ]);
            }

            $copilotRequest = CopilotRequest::query()
                ->lockForUpdate()
                ->findOrFail($data->copilotRequestId);

            if ($copilotRequest->message()->exists()) {
                throw new CopilotRequestAlreadyUsedException;
            }

            if ($copilotRequest->scene_id !== $scene->id
                || ! $this->copilotMatchesMessage($copilotRequest, $authorCharacterId)) {
                throw new CopilotRequestMismatchException;
            }

            $draftIndex = (int) $data->copilotDraftIndex;
            $this->assertCopilotDraftExists($copilotRequest, $draftIndex);

            $message = $user->messages()->create([
                'scene_id' => $scene->id,
                'body' => $data->body,
                'npc_name' => $npcName,
                'author_character_id' => $authorCharacterId,
                'copilot_request_id' => $copilotRequest->id,
                'is_ooc' => $data->isOoc,
            ]);

            $copilotRequest->update([
                'selected_draft_index' => $draftIndex,
            ]);

            return $message;
        });

        $this->diaryScheduler->scheduleForScene($scene);

        return $message->load('user:id,name');
    }

    public function postSystem(Scene $scene, User $storyteller, string $body): Message
    {
        return $storyteller->messages()->create([
            'scene_id' => $scene->id,
            'body' => $body,
            'npc_name' => null,
            'author_character_id' => null,
            'copilot_request_id' => null,
            'is_ooc' => false,
            'is_system' => true,
        ])->load('user:id,name');
    }

    /**
     * @param  Collection<int, Message>  $messages
     * @return array<int, array{name: string, type: CharacterType, playable: bool}>
     */
    public function characterLookup(Collection $messages, User $viewer): array
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

        $lookup = [];

        foreach ($characters as $id => $character) {
            $lookup[(int) $id] = [
                'name' => $character->entity === null
                    ? ''
                    : (string) $character->entity->canonical_name,
                'type' => $character->character_type,
                'playable' => $character->isPlayableBy($viewer),
            ];
        }

        return $lookup;
    }

    private function copilotMatchesMessage(
        CopilotRequest $copilotRequest,
        ?int $authorCharacterId,
    ): bool {
        return $copilotRequest->character_id !== null
            && (int) $copilotRequest->character_id === (int) $authorCharacterId;
    }

    private function assertCopilotDraftExists(CopilotRequest $copilotRequest, int $draftIndex): void
    {
        $drafts = $copilotRequest->getAttribute('drafts');

        if (! is_array($drafts) || ! array_key_exists($draftIndex, $drafts)) {
            throw new CopilotDraftNotFoundException;
        }
    }
}
