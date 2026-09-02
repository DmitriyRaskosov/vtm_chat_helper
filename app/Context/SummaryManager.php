<?php

namespace App\Context;

use App\Enums\ContextSummaryLevel;
use App\Enums\ContextSummarySourceType;
use App\Jobs\IndexRagSummaryJob;
use App\Models\ContextSummary;
use App\Models\ContextSummarySource;
use App\Models\GameSession;
use App\Models\Message;
use App\Models\Scene;
use App\Models\SceneContextState;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SummaryManager
{
    public function __construct(
        private MessageWindowSelector $windows,
        private SummaryGenerator $generator,
    ) {}

    public function summarizeAvailableL0(int $sceneId, bool $force = false): void
    {
        while ($this->summarizeNextL0($sceneId, $force)) {
            //
        }

        $this->summarizeAvailableL1($sceneId);
    }

    public function summarizeAvailableL1(int $sceneId): void
    {
        while ($this->summarizeNextL1($sceneId)) {
            //
        }
    }

    public function finalizeScene(int $sceneId): ?ContextSummary
    {
        $scene = Scene::query()->with('gameSession')->findOrFail($sceneId);
        $this->summarizeAvailableL0($sceneId, true);

        $l1 = ContextSummary::query()
            ->with('sources')
            ->where('scene_id', $sceneId)
            ->where('level', ContextSummaryLevel::L1)
            ->orderBy('first_message_id')
            ->get();

        $coveredL0Ids = $l1
            ->flatMap(fn (ContextSummary $summary) => $summary->sources
                ->where('source_type', ContextSummarySourceType::Summary)
                ->pluck('source_id'))
            ->map(fn ($id): int => (int) $id)
            ->all();

        $remainingL0 = ContextSummary::query()
            ->where('scene_id', $sceneId)
            ->where('level', ContextSummaryLevel::L0)
            ->when($coveredL0Ids !== [], fn ($query) => $query->whereNotIn('id', $coveredL0Ids))
            ->orderBy('first_message_id')
            ->get();

        /** @var Collection<int, ContextSummary> $sources */
        $sources = $l1
            ->concat($remainingL0)
            ->sortBy('first_message_id')
            ->values();

        if ($sources->isEmpty()) {
            return null;
        }

        $summary = $this->createFromSummaries(
            ContextSummaryLevel::SceneFinal,
            $scene->game_session_id,
            $scene->id,
            $scene->title,
            $sources,
        );

        $this->updateSessionSummary($scene->gameSession);

        return $summary;
    }

    private function summarizeNextL0(int $sceneId, bool $force): bool
    {
        $maxMessages = (int) config('context.l0.max_messages', 50);

        $snapshot = DB::transaction(function () use ($sceneId, $maxMessages, $force): ?array {
            $scene = Scene::query()->with('gameSession')->findOrFail($sceneId);
            SceneContextState::query()->firstOrCreate(['scene_id' => $sceneId]);
            $state = SceneContextState::query()->where('scene_id', $sceneId)->lockForUpdate()->firstOrFail();

            $messages = Message::query()
                ->with('user:id,name')
                ->where('scene_id', $sceneId)
                ->when(
                    $state->last_summarized_message_id !== null,
                    fn ($query) => $query->where('id', '>', $state->last_summarized_message_id),
                )
                ->orderBy('id')
                ->limit($maxMessages + 1)
                ->get();

            $window = $this->windows->select($messages);
            $ready = ! $window->isEmpty() && (
                $force
                || $window->oversized
                || $window->messageCount() >= $maxMessages
                || $messages->count() > $window->messageCount()
            );

            if (! $ready) {
                return null;
            }

            return [
                'scene' => $scene,
                'cursor' => $state->last_summarized_message_id,
                'messages' => $window->messages,
                'oversized' => $window->oversized,
                'token_count' => $window->tokenCount,
            ];
        });

        if ($snapshot === null) {
            return false;
        }

        /** @var Scene $scene */
        $scene = $snapshot['scene'];
        /** @var Collection<int, Message> $messages */
        $messages = $snapshot['messages'];
        $generated = $this->generator->generate(
            ContextSummaryLevel::L0,
            $scene->title,
            $messages->map(fn (Message $message): string => $message->displayAuthor().': '.$message->body)->all(),
        );
        $sourceIds = $messages->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $hash = $this->sourceHash(ContextSummaryLevel::L0, $scene->id, $sourceIds);

        $summary = DB::transaction(function () use ($snapshot, $scene, $generated, $sourceIds, $hash): ?ContextSummary {
            $state = SceneContextState::query()->where('scene_id', $scene->id)->lockForUpdate()->firstOrFail();
            if ($state->last_summarized_message_id !== $snapshot['cursor']) {
                return null;
            }

            $summary = ContextSummary::query()->firstOrCreate(
                ['source_hash' => $hash],
                [
                    'game_session_id' => $scene->game_session_id,
                    'scene_id' => $scene->id,
                    'level' => ContextSummaryLevel::L0,
                    'first_message_id' => $sourceIds[0],
                    'last_message_id' => $sourceIds[array_key_last($sourceIds)],
                    'content' => $generated->content,
                    'metadata' => array_merge($generated->metadata, [
                        'source_token_estimate' => $snapshot['token_count'],
                        'oversized' => $snapshot['oversized'],
                    ]),
                    'model' => config('ollama.chat_model'),
                    'prompt_version' => SummaryGenerator::PROMPT_VERSION,
                ],
            );

            if ($summary->wasRecentlyCreated) {
                $this->createSources($summary, ContextSummarySourceType::Message, $sourceIds);
            }

            $state->update(['last_summarized_message_id' => $summary->last_message_id]);

            return $summary;
        });

        if ($summary !== null) {
            IndexRagSummaryJob::dispatch($summary->id);
        }

        return true;
    }

    private function summarizeNextL1(int $sceneId): bool
    {
        $threshold = (int) config('context.l1.summary_count', 5);
        if ($threshold < 2) {
            return false;
        }

        $snapshot = DB::transaction(function () use ($sceneId, $threshold): ?array {
            $scene = Scene::query()->findOrFail($sceneId);
            SceneContextState::query()->firstOrCreate(['scene_id' => $sceneId]);
            $state = SceneContextState::query()->where('scene_id', $sceneId)->lockForUpdate()->firstOrFail();
            $summaries = ContextSummary::query()
                ->where('scene_id', $sceneId)
                ->where('level', ContextSummaryLevel::L0)
                ->when(
                    $state->last_l1_source_id !== null,
                    fn ($query) => $query->where('id', '>', $state->last_l1_source_id),
                )
                ->orderBy('id')
                ->limit($threshold)
                ->get();

            if ($summaries->count() < $threshold) {
                return null;
            }

            return ['scene' => $scene, 'cursor' => $state->last_l1_source_id, 'summaries' => $summaries];
        });

        if ($snapshot === null) {
            return false;
        }

        /** @var Scene $scene */
        $scene = $snapshot['scene'];
        /** @var Collection<int, ContextSummary> $summaries */
        $summaries = $snapshot['summaries'];
        $generated = $this->generator->generate(
            ContextSummaryLevel::L1,
            $scene->title,
            $summaries->pluck('content')->all(),
        );
        $sourceIds = $summaries->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $hash = $this->sourceHash(ContextSummaryLevel::L1, $scene->id, $sourceIds);

        $summary = DB::transaction(function () use ($snapshot, $scene, $summaries, $generated, $sourceIds, $hash): ?ContextSummary {
            $state = SceneContextState::query()->where('scene_id', $scene->id)->lockForUpdate()->firstOrFail();
            if ($state->last_l1_source_id !== $snapshot['cursor']) {
                return null;
            }

            $summary = ContextSummary::query()->firstOrCreate(
                ['source_hash' => $hash],
                [
                    'game_session_id' => $scene->game_session_id,
                    'scene_id' => $scene->id,
                    'level' => ContextSummaryLevel::L1,
                    'first_message_id' => $summaries->first()->first_message_id,
                    'last_message_id' => $summaries->last()->last_message_id,
                    'content' => $generated->content,
                    'metadata' => $generated->metadata,
                    'model' => config('ollama.chat_model'),
                    'prompt_version' => SummaryGenerator::PROMPT_VERSION,
                ],
            );

            if ($summary->wasRecentlyCreated) {
                $this->createSources($summary, ContextSummarySourceType::Summary, $sourceIds);
            }

            $state->update(['last_l1_source_id' => $sourceIds[array_key_last($sourceIds)]]);

            return $summary;
        });

        if ($summary !== null) {
            IndexRagSummaryJob::dispatch($summary->id);
        }

        return true;
    }

    /**
     * @param  Collection<int, ContextSummary>  $sources
     */
    private function createFromSummaries(
        ContextSummaryLevel $level,
        int $gameSessionId,
        ?int $sceneId,
        string $scopeTitle,
        Collection $sources,
    ): ContextSummary {
        $sourceIds = $sources->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $hash = $this->sourceHash($level, $sceneId ?? $gameSessionId, $sourceIds);
        $existing = ContextSummary::query()->where('source_hash', $hash)->first();
        if ($existing !== null) {
            return $existing;
        }

        $generated = $this->generator->generate($level, $scopeTitle, $sources->pluck('content')->all());
        $summary = DB::transaction(function () use (
            $hash,
            $gameSessionId,
            $sceneId,
            $level,
            $sources,
            $generated,
            $sourceIds,
        ): ContextSummary {
            $summary = ContextSummary::query()->firstOrCreate(
                ['source_hash' => $hash],
                [
                    'game_session_id' => $gameSessionId,
                    'scene_id' => $sceneId,
                    'level' => $level,
                    'first_message_id' => $sources->min('first_message_id'),
                    'last_message_id' => $sources->max('last_message_id'),
                    'content' => $generated->content,
                    'metadata' => $generated->metadata,
                    'model' => config('ollama.chat_model'),
                    'prompt_version' => SummaryGenerator::PROMPT_VERSION,
                ],
            );

            if ($summary->wasRecentlyCreated) {
                $this->createSources($summary, ContextSummarySourceType::Summary, $sourceIds);
            }

            return $summary;
        });

        IndexRagSummaryJob::dispatch($summary->id);

        return $summary;
    }

    private function updateSessionSummary(GameSession $session): ?ContextSummary
    {
        $finals = ContextSummary::query()
            ->join('scenes', 'scenes.id', '=', 'context_summaries.scene_id')
            ->where('context_summaries.game_session_id', $session->id)
            ->where('context_summaries.level', ContextSummaryLevel::SceneFinal)
            ->orderBy('scenes.position')
            ->select('context_summaries.*')
            ->get();

        if ($finals->isEmpty()) {
            return null;
        }

        return $this->createFromSummaries(
            ContextSummaryLevel::Session,
            $session->id,
            null,
            $session->title,
            $finals,
        );
    }

    /**
     * @param  list<int>  $sourceIds
     */
    private function createSources(
        ContextSummary $summary,
        ContextSummarySourceType $type,
        array $sourceIds,
    ): void {
        foreach ($sourceIds as $position => $sourceId) {
            ContextSummarySource::query()->create([
                'context_summary_id' => $summary->id,
                'source_type' => $type,
                'source_id' => $sourceId,
                'position' => $position,
            ]);
        }
    }

    /**
     * @param  list<int>  $sourceIds
     */
    private function sourceHash(ContextSummaryLevel $level, int $scopeId, array $sourceIds): string
    {
        return hash('sha256', json_encode([$level->value, $scopeId, $sourceIds], JSON_THROW_ON_ERROR));
    }
}
