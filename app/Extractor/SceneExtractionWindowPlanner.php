<?php

namespace App\Extractor;

use App\Models\Chronicle;
use App\Models\Message;
use App\Models\Scene;
use App\Models\WorldRelationType;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class SceneExtractionWindowPlanner
{
    public function __construct(
        private SceneSliceBuilder $sceneSlice,
        private EntityCatalogBuilder $catalog,
        private ExtractionPromptBuilder $prompts,
        private ExtractorTokenBudget $tokenBudget,
    ) {}

    /**
     * @return array{
     *     from_message_id: int,
     *     to_message_id: int,
     *     message_ids: list<int>
     * }|null
     */
    public function planNextWindow(Scene $scene, Chronicle $chronicle, bool $allowPartialTail = false): ?array
    {
        $messages = $this->unextractedMessages($scene);

        if ($messages->isEmpty()) {
            return null;
        }

        $windowIds = $this->buildWindowIds($scene, $chronicle, $messages);

        if ($windowIds === []) {
            return null;
        }

        if (! $allowPartialTail && ! $this->isCompleteWindow($scene, $chronicle, $windowIds, $messages)) {
            return null;
        }

        return $this->windowPayload($windowIds);
    }

    /**
     * @return array{
     *     from_message_id: int,
     *     to_message_id: int,
     *     message_ids: list<int>
     * }
     */
    public function windowForRange(Scene $scene, int $fromMessageId, int $toMessageId): array
    {
        $ids = Message::query()
            ->where('scene_id', $scene->id)
            ->whereBetween('id', [$fromMessageId, $toMessageId])
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($ids === []) {
            throw new InvalidArgumentException('Scene window has no messages in the requested range.');
        }

        if ($ids[0] !== $fromMessageId || $ids[array_key_last($ids)] !== $toMessageId) {
            throw new InvalidArgumentException('Scene window range must match contiguous message ids.');
        }

        return $this->windowPayload($ids);
    }

    /**
     * @return Collection<int, Message>
     */
    private function unextractedMessages(Scene $scene): Collection
    {
        return Message::query()
            ->where('scene_id', $scene->id)
            ->when(
                $scene->last_extracted_to_message_id !== null,
                fn ($query) => $query->where('id', '>', (int) $scene->last_extracted_to_message_id),
            )
            ->orderBy('id')
            ->get(['id', 'body']);
    }

    /**
     * @param  Collection<int, Message>  $messages
     * @return list<int>
     */
    private function buildWindowIds(Scene $scene, Chronicle $chronicle, Collection $messages): array
    {
        $limit = max(1, (int) config('extractor.scene_message_limit'));
        $windowIds = [];

        foreach ($messages as $message) {
            if (count($windowIds) >= $limit) {
                break;
            }

            $candidateIds = [...$windowIds, (int) $message->id];

            if (! $this->fitsSceneWindow($scene, $chronicle, $candidateIds)) {
                if ($windowIds === []) {
                    throw new InvalidArgumentException(
                        'Source slice is too large for the extractor context window. Shorten the text or catalog.',
                    );
                }

                break;
            }

            $windowIds = $candidateIds;
        }

        return $windowIds;
    }

    /**
     * @param  list<int>  $windowIds
     * @param  Collection<int, Message>  $messages
     */
    private function isCompleteWindow(
        Scene $scene,
        Chronicle $chronicle,
        array $windowIds,
        Collection $messages,
    ): bool {
        $limit = max(1, (int) config('extractor.scene_message_limit'));

        if (count($windowIds) >= $limit) {
            return true;
        }

        $lastWindowId = $windowIds[array_key_last($windowIds)];
        $remaining = $messages->filter(fn (Message $message): bool => (int) $message->id > $lastWindowId);

        if ($remaining->isEmpty()) {
            return false;
        }

        $nextId = (int) $remaining->first()->id;
        $withNext = [...$windowIds, $nextId];

        return ! $this->fitsSceneWindow($scene, $chronicle, $withNext);
    }

    /**
     * @param  list<int>  $messageIds
     */
    private function fitsSceneWindow(Scene $scene, Chronicle $chronicle, array $messageIds): bool
    {
        $slice = $this->sceneSlice->buildFromMessageIds($scene, $messageIds);
        $participantIds = $scene->participants()
            ->where('is_current', true)
            ->pluck('character_id')
            ->map(fn ($id): int => (int) $id)
            ->all();
        $catalogLines = $this->catalog->build(
            $chronicle,
            $this->sceneSlice->formatForPrompt($slice),
            $participantIds,
        );
        $relationKeys = WorldRelationType::query()
            ->where('enabled', true)
            ->orderBy('key')
            ->pluck('key')
            ->all();

        $messages = $this->prompts->buildForScene(
            $this->sceneSlice->formatForPrompt($slice),
            $catalogLines,
            $relationKeys,
            false,
        );

        return $this->tokenBudget->fits($messages);
    }

    /**
     * @param  list<int>  $messageIds
     * @return array{
     *     from_message_id: int,
     *     to_message_id: int,
     *     message_ids: list<int>
     * }
     */
    private function windowPayload(array $messageIds): array
    {
        return [
            'from_message_id' => $messageIds[0],
            'to_message_id' => $messageIds[array_key_last($messageIds)],
            'message_ids' => $messageIds,
        ];
    }
}
