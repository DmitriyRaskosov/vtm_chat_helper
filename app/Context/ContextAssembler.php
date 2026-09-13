<?php

namespace App\Context;

use App\Context\Providers\BiographyProvider;
use App\Context\Providers\ClosingInstructionProvider;
use App\Context\Providers\ContextProvider;
use App\Context\Providers\DirectRelationsProvider;
use App\Context\Providers\MemoryGraphProvider;
use App\Context\Providers\NpcIdentityProvider;
use App\Context\Providers\RecentMessagesProvider;
use App\Context\Providers\RulesProvider;
use App\Context\Providers\SceneProvider;
use App\Context\Providers\StatusProvider;
use App\Context\Providers\StorytellerPromptProvider;
use App\Context\Providers\SystemPromptProvider;
use App\Context\Providers\WorldLoreProvider;
use App\Models\Character;
use App\Models\Scene;
use App\Models\WorldEntity;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class ContextAssembler
{
    public const VERSION = 'context-assembler-v1';

    public const PROMPT_VERSION = 'npc-drafts-v6';

    /**
     * @var list<string>
     */
    public const USER_ORDER = [
        'npc_identity',
        'scene',
        'status',
        'storyteller_prompt',
        'recent_messages',
        'direct_relations',
        'biography',
        'memory_graph',
        'world_lore',
        'rules',
        'closing',
    ];

    /**
     * @var list<string>
     */
    private const OPTIONAL = [
        'direct_relations',
        'biography',
        'memory_graph',
        'world_lore',
        'rules',
    ];

    /**
     * @var array<string, ContextProvider>
     */
    private array $providers;

    public function __construct(
        private TokenEstimator $estimator,
        SystemPromptProvider $system,
        NpcIdentityProvider $identity,
        SceneProvider $scene,
        StatusProvider $status,
        StorytellerPromptProvider $prompt,
        private RecentMessagesProvider $recentMessages,
        DirectRelationsProvider $relations,
        BiographyProvider $biography,
        MemoryGraphProvider $memory,
        WorldLoreProvider $world,
        RulesProvider $rules,
        ClosingInstructionProvider $closing,
    ) {
        $this->providers = [
            $system->key() => $system,
            $identity->key() => $identity,
            $scene->key() => $scene,
            $status->key() => $status,
            $prompt->key() => $prompt,
            $this->recentMessages->key() => $this->recentMessages,
            $relations->key() => $relations,
            $biography->key() => $biography,
            $memory->key() => $memory,
            $world->key() => $world,
            $rules->key() => $rules,
            $closing->key() => $closing,
        ];
    }

    public function assemble(ContextRequest $request): ContextBuild
    {
        $budget = (int) config('context.copilot.max_input_tokens', 12000);
        $contextLength = (int) config('ollama.context_length', 16384);
        $maxOutputTokens = (int) config('ollama.max_output_tokens', 3000);
        $assembly = $this->resolve($request);
        $timings = [];

        $sections = $this->requiredWithoutHistory($assembly, $timings);
        $messages = $this->toLlmMessages($sections);

        if (
            $budget < 1
            || $maxOutputTokens < 1
            || $budget + $maxOutputTokens > $contextLength
            || $this->estimateMessages($messages) > $budget
        ) {
            throw new InvalidArgumentException('Copilot token limits are invalid or too small for the required prompt.');
        }

        $historyStarted = hrtime(true);
        $history = $this->recentMessages->candidates($assembly);
        $includedHistory = collect();

        foreach ($history->reverse() as $message) {
            $candidate = collect([$message])->concat($includedHistory);
            $trial = $sections;
            $trial['recent_messages'] = $this->recentMessages->assembleFrom(
                $candidate,
                $this->sectionMax('recent_messages'),
            );
            if ($this->estimateMessages($this->toLlmMessages($trial)) > $budget) {
                break;
            }
            $includedHistory = $candidate;
            $sections = $trial;
        }
        $timings['recent_messages'] = (int) round((hrtime(true) - $historyStarted) / 1_000_000);
        Log::debug('context.section', [
            'key' => 'recent_messages',
            'ms' => $timings['recent_messages'],
            'tokens' => $sections['recent_messages']->tokenEstimate,
            'included' => $sections['recent_messages']->included,
            'scene_id' => (int) $assembly->scene->id,
        ]);

        $leftover = $budget - $this->estimateMessages($this->toLlmMessages($sections));

        foreach (self::OPTIONAL as $key) {
            if ($leftover < 1) {
                $sections[$key] = ContextSection::omitted($key, ['reason' => 'budget']);

                continue;
            }

            $section = $this->assembleTimed($key, $assembly, min($leftover, $this->sectionMax($key)), $timings);
            if (! $section->included) {
                $sections[$key] = $section;

                continue;
            }

            $trial = $sections;
            $trial[$key] = $section;
            $estimate = $this->estimateMessages($this->toLlmMessages($trial));
            if ($estimate > $budget) {
                $sections[$key] = ContextSection::omitted($key, [
                    ...$section->provenance,
                    'reason' => 'budget',
                ]);

                continue;
            }

            $sections[$key] = $section;
            $leftover = $budget - $estimate;
        }

        $messages = $this->toLlmMessages($sections);
        $includedMessageIds = $includedHistory
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        return new ContextBuild($messages, $this->metadata(
            $assembly,
            $sections,
            $budget,
            $contextLength,
            $maxOutputTokens,
            $includedMessageIds,
            $history->count() - $includedHistory->count(),
            $timings,
        ));
    }

    private function resolve(ContextRequest $request): ContextAssembly
    {
        $scene = Scene::query()->with(['gameSession.chronicle'])->findOrFail($request->sceneId);
        $gameSession = $scene->gameSession;
        $chronicle = $gameSession->chronicle;
        $character = null;
        $entity = null;

        if ($request->characterId !== null) {
            $character = Character::query()->findOrFail($request->characterId);
            if ((int) $character->chronicle_id !== (int) $chronicle->id) {
                throw new InvalidArgumentException('Character does not belong to this chronicle.');
            }
            $entity = WorldEntity::query()->findOrFail($character->id);
        }

        return new ContextAssembly($request, $scene, $gameSession, $chronicle, $character, $entity);
    }

    /**
     * @param  array<string, int>  $timings
     * @return array<string, ContextSection>
     */
    private function requiredWithoutHistory(ContextAssembly $assembly, array &$timings): array
    {
        $keys = ['system', 'npc_identity', 'scene', 'status', 'storyteller_prompt', 'closing'];
        $sections = [];
        foreach ($keys as $key) {
            $sections[$key] = $this->assembleTimed($key, $assembly, $this->sectionMax($key), $timings);
        }
        $sections['recent_messages'] = ContextSection::omitted('recent_messages', [
            'message_ids' => [],
        ]);
        foreach (self::OPTIONAL as $key) {
            $sections[$key] = ContextSection::omitted($key, ['reason' => 'pending']);
        }

        return $sections;
    }

    /**
     * @param  array<string, ContextSection>  $sections
     * @return list<array{role: string, content: string}>
     */
    private function toLlmMessages(array $sections): array
    {
        $system = $sections['system']->content ?? '';
        $parts = [];
        foreach (self::USER_ORDER as $key) {
            $section = $sections[$key] ?? null;
            if ($section instanceof ContextSection && $section->included && $section->content !== '') {
                $parts[] = $section->content;
            }
        }

        return [
            [
                'role' => 'system',
                'content' => $system,
            ],
            [
                'role' => 'user',
                'content' => implode("\n\n", $parts),
            ],
        ];
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     */
    private function estimateMessages(array $messages): int
    {
        return array_sum(array_map(
            fn (array $message): int => $this->estimator->estimate($message['content']),
            $messages,
        ));
    }

    /**
     * @param  array<string, int>  $timings
     */
    private function assembleTimed(string $key, ContextAssembly $assembly, int $budget, array &$timings): ContextSection
    {
        $started = hrtime(true);
        $section = $this->providers[$key]->assemble($assembly, $budget);
        $timings[$key] = (int) round((hrtime(true) - $started) / 1_000_000);
        Log::debug('context.section', [
            'key' => $key,
            'ms' => $timings[$key],
            'tokens' => $section->tokenEstimate,
            'included' => $section->included,
            'scene_id' => (int) $assembly->scene->id,
        ]);

        return $section;
    }

    /**
     * @param  array<string, ContextSection>  $sections
     * @param  list<int>  $includedMessageIds
     * @param  array<string, int>  $timings
     * @return array<string, mixed>
     */
    private function metadata(
        ContextAssembly $assembly,
        array $sections,
        int $budget,
        int $contextLength,
        int $maxOutputTokens,
        array $includedMessageIds,
        int $excludedRawMessageCount,
        array $timings = [],
    ): array {
        $sectionMeta = [];
        $counts = [];
        foreach (['system', ...self::USER_ORDER] as $key) {
            $section = $sections[$key] ?? ContextSection::omitted($key);
            $sectionMeta[$key] = [
                'included' => $section->included,
                'tokens' => $section->tokenEstimate,
                'truncation' => $section->truncation,
                'provenance' => $section->provenance,
                'elapsed_ms' => $timings[$key] ?? null,
            ];
            $counts[$key] = $section->tokenEstimate;
        }

        $statusRevision = $sections['status']->provenance['revision'] ?? null;
        $biographyVersion = $sections['biography']->provenance['biography_version'] ?? null;
        $contextRevision = $sections['scene']->provenance['context_revision'] ?? null;

        return [
            'builder_version' => self::VERSION,
            'prompt_version' => self::PROMPT_VERSION,
            'input_token_budget' => $budget,
            'input_token_estimate' => $this->estimateMessages($this->toLlmMessages($sections)),
            'token_estimator_version' => $this->estimator->version(),
            'history_limit' => (int) config('copilot.history_limit'),
            'draft_count' => $assembly->request->draftCount,
            'ollama_context_length' => $contextLength,
            'ollama_max_output_tokens' => $maxOutputTokens,
            'scene_id' => (int) $assembly->scene->id,
            'game_session_id' => (int) $assembly->gameSession->id,
            'chronicle_id' => (int) $assembly->chronicle->id,
            'character_id' => $assembly->character?->id,
            'storyteller_id' => $assembly->request->storytellerId,
            'included_raw_message_ids' => $includedMessageIds,
            'excluded_raw_message_count' => $excludedRawMessageCount,
            'section_token_counts' => $counts,
            'sections' => $sectionMeta,
            'data_versions' => [
                'status_revision' => $statusRevision,
                'biography_version' => $biographyVersion,
                'context_revision' => $contextRevision,
            ],
            'filters' => [
                'history_limit' => (int) config('copilot.history_limit'),
                'memory_graphrag' => config('retrieval.memory_graphrag'),
                'world_graphrag' => config('retrieval.world_graphrag'),
            ],
        ];
    }

    private function sectionMax(string $key): int
    {
        return max(1, (int) config("context.assembler.sections.{$key}.max", 10000));
    }
}
