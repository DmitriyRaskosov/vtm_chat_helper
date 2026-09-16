<?php

namespace App\Extractor;

use App\Enums\ExtractionRunStatus;
use App\Enums\ExtractionSourceType;
use App\Enums\ExtractionTrigger;
use App\Enums\LoreEntryStatus;
use App\Llm\ExtractorChatProvider;
use App\Models\Character;
use App\Models\CharacterBiography;
use App\Models\Chronicle;
use App\Models\ExtractionRun;
use App\Models\LoreEntry;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use App\Models\WorldRelationType;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class GraphExtractorService
{
    public function __construct(
        private ExtractorChatProvider $chat,
        private EntityCatalogBuilder $catalog,
        private ExtractionPromptBuilder $prompts,
        private ExtractionResponseParser $parser,
        private ExtractionCandidateMatcher $matcher,
        private BiographySliceBuilder $biographySlice,
        private SceneSliceBuilder $sceneSlice,
        private SceneExtractionWindowPlanner $windowPlanner,
        private LoreExtractionWindowPlanner $loreWindowPlanner,
        private ExtractorTokenBudget $tokenBudget,
    ) {}

    public function runFromLore(
        LoreEntry $entry,
        Chronicle $chronicle,
        User $user,
        bool $reparse = false,
    ): ExtractionRun {
        if ($entry->status === LoreEntryStatus::Archived) {
            throw new InvalidArgumentException('Cannot extract from archived lore entry.');
        }

        if ((int) $entry->chronicle_id !== (int) $chronicle->id) {
            throw new InvalidArgumentException('Lore entry does not belong to this chronicle.');
        }

        $this->assertDriverEnabled();

        if ($reparse) {
            $this->supersedeAllPriorLoreRuns($chronicle, $entry);
        }

        $window = $this->loreWindowPlanner->planNextWindow($entry);
        if ($window === null) {
            throw new InvalidArgumentException('Lore entry has no text left to extract.');
        }

        $entry->loadMissing('entities');
        $catalogLines = $this->catalog->build(
            $chronicle,
            $window['slice_text'],
            $entry->entities->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'lore',
        );
        $relationKeys = $this->enabledRelationKeys();

        $messages = $this->prompts->build(
            $window['slice_text'],
            $catalogLines,
            $relationKeys,
        );

        $this->loreWindowPlanner->assertWindowFits($messages, $this->tokenBudget);

        return $this->executeRun(
            chronicle: $chronicle,
            user: $user,
            sourceType: ExtractionSourceType::Lore,
            sourceId: $entry->id,
            llmMessages: $messages,
            profile: 'lore',
            matchCallback: fn (array $parsed): array => $this->matcher->match(
                $parsed,
                $chronicle,
                ExtractionSourceType::Lore,
            ),
            fromCharOffset: $window['from_char_offset'],
            toCharOffset: $window['to_char_offset'],
            trigger: $reparse ? ExtractionTrigger::Reparse : null,
        );
    }

    public function runFromBiography(Character $character, Chronicle $chronicle, User $user): ExtractionRun
    {
        if (! $character->is_active) {
            throw new InvalidArgumentException('Cannot extract from archived character.');
        }

        if ((int) $character->chronicle_id !== (int) $chronicle->id) {
            throw new InvalidArgumentException('Character does not belong to this chronicle.');
        }

        $biography = CharacterBiography::query()
            ->where('character_id', $character->id)
            ->first();

        if ($biography === null || $this->biographySlice->isEmpty($biography)) {
            throw new InvalidArgumentException('Character has no biography text to extract.');
        }

        $this->assertDriverEnabled();

        $characterName = WorldEntity::query()->whereKey($character->id)->value('canonical_name');
        if (! is_string($characterName) || $characterName === '') {
            throw new InvalidArgumentException('Character name is required.');
        }

        $messages = $this->prompts->buildForBiography(
            $this->biographySlice->build($biography),
            $characterName,
        );

        return $this->executeRun(
            chronicle: $chronicle,
            user: $user,
            sourceType: ExtractionSourceType::Biography,
            sourceId: $character->id,
            llmMessages: $messages,
            profile: 'biography',
            matchCallback: fn (array $parsed): array => $this->matcher->match(
                $parsed,
                $chronicle,
                ExtractionSourceType::Biography,
            ),
        );
    }

    public function runFromSceneManual(
        Scene $scene,
        Chronicle $chronicle,
        User $user,
        ?int $fromMessageId = null,
        ?int $toMessageId = null,
    ): ExtractionRun {
        $scene->loadMissing('gameSession');

        if ((int) $scene->gameSession->chronicle_id !== (int) $chronicle->id) {
            throw new InvalidArgumentException('Scene does not belong to this chronicle.');
        }

        if ($this->sceneSlice->isEmpty($scene)) {
            throw new InvalidArgumentException('Scene has no messages to extract.');
        }

        $this->assertDriverEnabled();

        if ($fromMessageId !== null && $toMessageId !== null) {
            $window = $this->windowPlanner->windowForRange($scene, $fromMessageId, $toMessageId);
        } else {
            $window = $this->windowPlanner->planNextWindow($scene, $chronicle, allowPartialTail: true);
            if ($window === null) {
                throw new InvalidArgumentException('Scene has no messages to extract.');
            }
        }

        return $this->runFromSceneWindow(
            $scene,
            $chronicle,
            $user,
            $window['from_message_id'],
            $window['to_message_id'],
            ExtractionTrigger::Manual,
        );
    }

    public function runFromSceneWindow(
        Scene $scene,
        Chronicle $chronicle,
        User $user,
        int $fromMessageId,
        int $toMessageId,
        ExtractionTrigger $trigger,
    ): ExtractionRun {
        $scene->loadMissing('gameSession');

        if ((int) $scene->gameSession->chronicle_id !== (int) $chronicle->id) {
            throw new InvalidArgumentException('Scene does not belong to this chronicle.');
        }

        $this->assertDriverEnabled();

        $window = $this->windowPlanner->windowForRange($scene, $fromMessageId, $toMessageId);
        $slice = $this->sceneSlice->buildFromMessageIds($scene, $window['message_ids']);
        $catalogLines = $this->catalogForScene($scene, $chronicle, $slice);
        $relationKeys = $this->enabledRelationKeys();

        $messages = $this->prompts->buildForScene(
            $this->sceneSlice->formatForPrompt($slice),
            $catalogLines,
            $relationKeys,
            false,
        );

        $run = ExtractionRun::query()->create([
            'chronicle_id' => $chronicle->id,
            'source_type' => ExtractionSourceType::Scene,
            'source_id' => $scene->id,
            'status' => ExtractionRunStatus::Running,
            'trigger' => $trigger,
            'from_message_id' => $fromMessageId,
            'to_message_id' => $toMessageId,
            'driver' => (string) config('extractor.driver'),
            'model' => (string) config('extractor.ollama_model'),
            'raw_response' => [],
            'candidates' => $this->emptyCandidates(),
            'user_id' => $user->id,
        ]);

        ExtractionRun::query()
            ->where('source_type', ExtractionSourceType::Scene)
            ->where('source_id', $scene->id)
            ->where('from_message_id', $fromMessageId)
            ->where('to_message_id', $toMessageId)
            ->where('status', ExtractionRunStatus::Failed)
            ->where('id', '!=', $run->id)
            ->update([
                'status' => ExtractionRunStatus::Superseded,
                'superseded_by_run_id' => $run->id,
            ]);

        try {
            $raw = $this->callModel($messages, 'scene');
            $parsed = $this->parseModelResponse($raw);
            $candidates = $this->matcher->match(
                $parsed,
                $chronicle,
                ExtractionSourceType::Scene,
                $slice['message_ids'],
            );

            $run->update([
                'status' => ExtractionRunStatus::NeedsReview,
                'raw_response' => $parsed,
                'candidates' => $candidates,
            ]);

            ExtractionRunCompletion::finalizeIfComplete($run->refresh());

            $scene->refresh();
            $cursor = $scene->last_extracted_to_message_id;
            if ($cursor === null || (int) $cursor < $toMessageId) {
                $scene->update([
                    'last_extracted_to_message_id' => $toMessageId,
                ]);
            }
        } catch (Throwable $e) {
            $run->update([
                'status' => ExtractionRunStatus::Failed,
                'raw_response' => ['error' => $e->getMessage()],
                'candidates' => $this->emptyCandidates(),
            ]);

            throw $e;
        }

        return $run->refresh();
    }

    public function reparseLoreRun(ExtractionRun $run, Chronicle $chronicle, User $user): ExtractionRun
    {
        if ($run->source_type !== ExtractionSourceType::Lore) {
            throw new InvalidArgumentException('Only lore extraction runs can be reparsed.');
        }

        if ($run->from_char_offset === null || $run->to_char_offset === null) {
            throw new InvalidArgumentException('Lore extraction run is missing char window.');
        }

        $entry = LoreEntry::query()->findOrFail($run->source_id);

        if ($entry->status === LoreEntryStatus::Archived) {
            throw new InvalidArgumentException('Cannot extract from archived lore entry.');
        }

        if ((int) $entry->chronicle_id !== (int) $chronicle->id) {
            throw new InvalidArgumentException('Lore entry does not belong to this chronicle.');
        }

        $this->assertDriverEnabled();

        $run->update([
            'status' => ExtractionRunStatus::Superseded,
        ]);

        $replacement = $this->runFromLoreWindow(
            $entry,
            $chronicle,
            $user,
            (int) $run->from_char_offset,
            (int) $run->to_char_offset,
        );

        $run->update([
            'superseded_by_run_id' => $replacement->id,
        ]);

        return $replacement;
    }

    public function reparseSceneRun(ExtractionRun $run, Chronicle $chronicle, User $user): ExtractionRun
    {
        if ($run->source_type !== ExtractionSourceType::Scene) {
            throw new InvalidArgumentException('Only scene extraction runs can be reparsed.');
        }

        if ($run->from_message_id === null || $run->to_message_id === null) {
            throw new InvalidArgumentException('Scene extraction run is missing message window.');
        }

        $scene = Scene::query()->with('gameSession')->findOrFail($run->source_id);

        if ((int) $scene->gameSession->chronicle_id !== (int) $chronicle->id) {
            throw new InvalidArgumentException('Scene does not belong to this chronicle.');
        }

        $run->update([
            'status' => ExtractionRunStatus::Superseded,
        ]);

        $replacement = $this->runFromSceneWindow(
            $scene,
            $chronicle,
            $user,
            (int) $run->from_message_id,
            (int) $run->to_message_id,
            ExtractionTrigger::Reparse,
        );

        $run->update([
            'superseded_by_run_id' => $replacement->id,
        ]);

        return $replacement;
    }

    public function runFromLoreWindow(
        LoreEntry $entry,
        Chronicle $chronicle,
        User $user,
        int $fromCharOffset,
        int $toCharOffset,
    ): ExtractionRun {
        if ($entry->status === LoreEntryStatus::Archived) {
            throw new InvalidArgumentException('Cannot extract from archived lore entry.');
        }

        if ((int) $entry->chronicle_id !== (int) $chronicle->id) {
            throw new InvalidArgumentException('Lore entry does not belong to this chronicle.');
        }

        $this->assertDriverEnabled();

        $text = (string) $entry->canonical_text;
        $sliceText = mb_substr($text, $fromCharOffset, $toCharOffset - $fromCharOffset);

        if ($sliceText === '') {
            throw new InvalidArgumentException('Lore entry has no text left to extract.');
        }

        $entry->loadMissing('entities');
        $catalogLines = $this->catalog->build(
            $chronicle,
            $sliceText,
            $entry->entities->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            'lore',
        );
        $relationKeys = $this->enabledRelationKeys();

        $messages = $this->prompts->build(
            $sliceText,
            $catalogLines,
            $relationKeys,
        );

        $this->loreWindowPlanner->assertWindowFits($messages, $this->tokenBudget);

        return $this->executeRun(
            chronicle: $chronicle,
            user: $user,
            sourceType: ExtractionSourceType::Lore,
            sourceId: $entry->id,
            llmMessages: $messages,
            profile: 'lore',
            matchCallback: fn (array $parsed): array => $this->matcher->match(
                $parsed,
                $chronicle,
                ExtractionSourceType::Lore,
            ),
            fromCharOffset: $fromCharOffset,
            toCharOffset: $toCharOffset,
            trigger: ExtractionTrigger::Reparse,
        );
    }

    private function supersedeAllPriorLoreRuns(Chronicle $chronicle, LoreEntry $entry): void
    {
        ExtractionRun::query()
            ->where('chronicle_id', $chronicle->id)
            ->where('source_type', ExtractionSourceType::Lore)
            ->where('source_id', $entry->id)
            ->whereIn('status', [
                ExtractionRunStatus::NeedsReview,
                ExtractionRunStatus::Reviewed,
                ExtractionRunStatus::Failed,
            ])
            ->update([
                'status' => ExtractionRunStatus::Superseded,
            ]);
    }

    /**
     * @param  callable(array<string, mixed>): array<string, mixed>  $matchCallback
     */
    private function executeRun(
        Chronicle $chronicle,
        User $user,
        ExtractionSourceType $sourceType,
        int $sourceId,
        array $llmMessages,
        string $profile,
        callable $matchCallback,
        ?int $fromCharOffset = null,
        ?int $toCharOffset = null,
        ?ExtractionTrigger $trigger = null,
    ): ExtractionRun {
        $raw = $this->callModel($llmMessages, $profile);
        $parsed = $this->parseModelResponse($raw);
        $candidates = $matchCallback($parsed);

        $run = ExtractionRun::query()->create([
            'chronicle_id' => $chronicle->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'status' => ExtractionRunStatus::NeedsReview,
            'trigger' => $trigger,
            'from_message_id' => null,
            'to_message_id' => null,
            'from_char_offset' => $fromCharOffset,
            'to_char_offset' => $toCharOffset,
            'driver' => (string) config('extractor.driver'),
            'model' => (string) config('extractor.ollama_model'),
            'raw_response' => $parsed,
            'candidates' => $candidates,
            'user_id' => $user->id,
        ]);

        $this->supersedePriorInboxRuns($chronicle, $sourceType, $sourceId, (int) $run->id, $fromCharOffset, $toCharOffset);

        return ExtractionRunCompletion::finalizeIfComplete($run);
    }

    private function supersedePriorInboxRuns(
        Chronicle $chronicle,
        ExtractionSourceType $sourceType,
        int $sourceId,
        int $replacementRunId,
        ?int $fromCharOffset = null,
        ?int $toCharOffset = null,
    ): void {
        $query = ExtractionRun::query()
            ->where('chronicle_id', $chronicle->id)
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->where('status', ExtractionRunStatus::NeedsReview)
            ->where('id', '!=', $replacementRunId);

        if ($sourceType === ExtractionSourceType::Lore) {
            $query
                ->where('from_char_offset', $fromCharOffset)
                ->where('to_char_offset', $toCharOffset);
        }

        $query->update([
            'status' => ExtractionRunStatus::Superseded,
            'superseded_by_run_id' => $replacementRunId,
        ]);
    }

    /**
     * @return array{
     *     mentions: list<array{name: string, kind: string}>,
     *     relations: list<array{source: string, target: string, key: string}>,
     *     events: list<array<string, mixed>>,
     *     memories: list<array<string, mixed>>
     * }
     */
    private function parseModelResponse(string $raw): array
    {
        try {
            return $this->parser->parse($raw);
        } catch (ExtractionParseException $e) {
            $directory = storage_path('extractor-fails');
            if (! is_dir($directory) && ! mkdir($directory, 0755, true) && ! is_dir($directory)) {
                throw $e;
            }

            $path = $directory.'/extractor-parse-fail-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.txt';
            file_put_contents($path, $raw);

            Log::warning('extractor.parse_failed', [
                'bytes' => strlen($raw),
                'json_error' => $e->jsonError,
                'file' => $path,
            ]);

            throw $e;
        }
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function callModel(array $messages, string $profile): string
    {
        $this->tokenBudget->assertFits($messages, $profile);
        $numPredict = $this->tokenBudget->effectiveNumPredict($messages, $profile);

        return $this->chat->chat($messages, [
            'temperature' => (float) config('extractor.temperature'),
            'num_predict' => $numPredict,
            'profile' => $profile,
        ]);
    }

    /**
     * @param  array{
     *     participants: list<array{name: string, type: string}>,
     *     messages: list<array{id: int, author: string, body: string}>,
     *     message_ids: list<int>,
     *     truncated: bool,
     *     total_messages: int
     * }  $slice
     * @return list<string>
     */
    private function catalogForScene(Scene $scene, Chronicle $chronicle, array $slice): array
    {
        $participantIds = $scene->participants()
            ->where('is_current', true)
            ->pluck('character_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return $this->catalog->build(
            $chronicle,
            $this->sceneSlice->formatForPrompt($slice),
            $participantIds,
            'scene',
        );
    }

    private function assertDriverEnabled(): void
    {
        $driver = (string) config('extractor.driver');
        if ($driver === 'none' || $driver !== 'ollama') {
            throw new ExtractionDisabledException;
        }
    }

    /**
     * @return list<string>
     */
    private function enabledRelationKeys(): array
    {
        return WorldRelationType::query()
            ->where('enabled', true)
            ->orderBy('key')
            ->pluck('key')
            ->all();
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    private function emptyCandidates(): array
    {
        return [
            'mentions' => [],
            'relations' => [],
            'events' => [],
            'memories' => [],
            'discarded' => [],
        ];
    }
}
