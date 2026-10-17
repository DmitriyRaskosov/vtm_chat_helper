<?php

namespace Tests\Feature;

use App\Context\ContextAssembler;
use App\Context\ContextBuild;
use App\Context\ContextBuilder;
use App\Context\ContextPass;
use App\Context\ContextRequest;
use App\Enums\WorldRelationKind;
use App\Models\CopilotRequest;
use App\Models\WorldRelation;
use InvalidArgumentException;
use Tests\Support\CreatesCopilotScene;
use Tests\Support\CreatesDiaryEntries;
use Tests\Support\CreatesSceneMessages;
use Tests\TestCase;

class ContextAssemblerTest extends TestCase
{
    use CreatesCopilotScene;
    use CreatesDiaryEntries;
    use CreatesSceneMessages;

    /**
     * @var array<string, string>
     */
    private const SECTION_MARKERS = [
        'npc_identity' => '## NPC identity',
        'scene' => '## Scene',
        'world_lore' => '## World',
        'storyteller_prompt' => '## Storyteller prompt',
        'recent_messages' => '## Scene speech',
        'diary' => '## Diary',
        'direct_relations' => '## Direct relations',
        'personality' => '## Personality',
        'biography' => '## Biography',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeEmbeddings();
        $this->setUpCopilotScene();
    }

    public function test_user_sections_follow_user_order_in_assembled_prompt(): void
    {
        $this->seedOptionalContextData();
        $this->createSceneMessage('Короткая реплика.');

        $build = $this->assembleReply();

        $userContent = $build->messages[1]['content'];
        $positions = [];
        foreach (ContextAssembler::USER_ORDER as $key) {
            $marker = self::SECTION_MARKERS[$key] ?? null;
            if ($marker === null || ! str_contains($userContent, $marker)) {
                continue;
            }
            $positions[$key] = mb_strpos($userContent, $marker);
        }

        $orderedKeys = array_keys($positions);
        usort($orderedKeys, fn (string $a, string $b): int => $positions[$a] <=> $positions[$b]);

        $expectedOrder = array_values(array_intersect(ContextAssembler::USER_ORDER, $orderedKeys));
        $this->assertSame($expectedOrder, $orderedKeys);
    }

    public function test_optional_sections_omitted_with_budget_reason_when_input_budget_is_tight(): void
    {
        $this->seedOptionalContextData();
        $this->createSceneMessage(str_repeat('Длинная реплика. ', 40));

        $build = $this->assembleReplyWithOptionalBudgetDrop('biography');

        $sections = $build->metadata['sections'];
        $this->assertTrue($sections['system']['included']);
        $this->assertTrue($sections['npc_identity']['included']);
        $this->assertTrue($sections['scene']['included']);
        $this->assertTrue($sections['storyteller_prompt']['included']);
        $this->assertTrue($sections['closing']['included']);

        $optionalDropped = false;
        foreach (['direct_relations', 'personality', 'biography', 'world_lore', 'diary'] as $key) {
            if (! $sections[$key]['included'] && ($sections[$key]['provenance']['reason'] ?? null) === 'budget') {
                $optionalDropped = true;
                break;
            }
        }
        $this->assertTrue($optionalDropped, 'Expected at least one optional section omitted with reason budget.');
        $this->assertLessThanOrEqual($build->metadata['input_token_budget'], $build->metadata['input_token_estimate']);
    }

    public function test_required_sections_always_included_when_messages_exist(): void
    {
        $this->createSceneMessage('Сообщение в сцене.');

        $build = $this->assembleReply();
        $sections = $build->metadata['sections'];

        foreach (['system', 'storyteller_prompt', 'npc_identity', 'scene', 'closing', 'recent_messages'] as $key) {
            $this->assertTrue($sections[$key]['included'], "Expected required section {$key} to be included.");
        }
    }

    public function test_throws_when_input_budget_too_small_for_required_prompt(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Copilot token limits are invalid or too small for the required prompt.');

        $this->assembleReply(5);
    }

    public function test_direct_relations_omitted_with_empty_reason_when_no_relations(): void
    {
        $build = $this->assembleReply();

        $section = $build->metadata['sections']['direct_relations'];
        $this->assertFalse($section['included']);
        $this->assertSame('empty', $section['provenance']['reason'] ?? null);
    }

    public function test_diary_section_limits_entries_and_truncates_by_section_max(): void
    {
        $retrievalPrompt = 'Тестовый промпт для retrieval';

        for ($i = 1; $i <= 5; $i++) {
            $this->createDiaryEntry(
                text: "Запись дневника номер {$i}: ".str_repeat('подробности ', 12),
                embeddingSourceText: $retrievalPrompt,
            );
        }

        $fullBudgetBuild = $this->assembleReply(prompt: $retrievalPrompt);
        $fullDiary = $fullBudgetBuild->metadata['sections']['diary'];
        $this->assertTrue($fullDiary['included']);
        $fullDiaryIds = $fullDiary['provenance']['diary_ids'] ?? [];
        $this->assertIsArray($fullDiaryIds);
        $this->assertGreaterThan(1, count($fullDiaryIds));
        $this->assertLessThanOrEqual(3, count($fullDiaryIds));

        config()->set('context.assembler.sections.diary.max', 55);
        $trimmedBuild = $this->assembleReply(prompt: $retrievalPrompt);
        $trimmedDiary = $trimmedBuild->metadata['sections']['diary'];
        $this->assertFalse($trimmedDiary['included']);
        $trimmedIds = $trimmedDiary['provenance']['diary_ids'] ?? [];
        $this->assertIsArray($trimmedIds);
        $this->assertLessThanOrEqual(3, count($trimmedIds));
    }

    public function test_topics_pass_skips_optional_sections_and_uses_topic_limits(): void
    {
        $this->seedOptionalContextData();
        $this->createSceneMessage('Реплика для истории.');

        $topics = app(ContextBuilder::class)->buildTopics(
            'Иван',
            'Тестовый промпт',
            $this->sceneId,
            3,
            $this->storyteller->id,
            $this->gameSession->id,
            $this->npcId,
        );

        $reply = app(ContextBuilder::class)->buildReply(
            'Иван',
            'Тестовый промпт',
            $this->sceneId,
            3,
            $this->storyteller->id,
            $this->gameSession->id,
            $this->npcId,
        );

        $this->assertSame('topics', $topics->metadata['pass']);
        $this->assertSame((int) config('copilot.topic_history_limit'), $topics->metadata['history_limit']);
        $this->assertSame((int) config('context.topic.max_input_tokens'), $topics->metadata['input_token_budget']);

        foreach (['direct_relations', 'personality', 'biography', 'world_lore', 'diary'] as $key) {
            $this->assertFalse($topics->metadata['sections'][$key]['included']);
            $this->assertSame('not_in_pass', $topics->metadata['sections'][$key]['provenance']['reason'] ?? null);
        }

        $this->assertSame('reply', $reply->metadata['pass']);
        $this->assertSame((int) config('copilot.history_limit'), $reply->metadata['history_limit']);
        $this->assertSame((int) config('context.copilot.max_input_tokens'), $reply->metadata['input_token_budget']);
        $this->assertGreaterThan($topics->metadata['input_token_estimate'], $reply->metadata['input_token_estimate']);
    }

    public function test_reply_pass_includes_optional_sections_when_budget_allows(): void
    {
        $this->seedOptionalContextData();
        $this->createSceneMessage('Реплика.');

        $build = $this->assembleReply();

        $this->assertTrue($build->metadata['sections']['direct_relations']['included']);
        $this->assertTrue($build->metadata['sections']['diary']['included']);
    }

    public function test_later_optional_sections_drop_before_earlier_ones_when_budget_is_exhausted(): void
    {
        $this->seedOptionalContextData();
        $this->createSceneMessage(str_repeat('Реплика. ', 30));

        $build = $this->assembleReplyWithOptionalBudgetDrop('biography', ['direct_relations']);
        $sections = $build->metadata['sections'];

        $this->assertTrue($sections['direct_relations']['included']);
        $this->assertFalse($sections['biography']['included']);
        $this->assertSame('budget', $sections['biography']['provenance']['reason'] ?? null);
    }

    public function test_recent_messages_stays_included_when_optional_sections_drop_for_budget(): void
    {
        $this->seedOptionalContextData();
        $this->createSceneMessage('Короткое сообщение.');

        $build = $this->assembleReplyWithOptionalBudgetDrop('biography');
        $sections = $build->metadata['sections'];

        $this->assertTrue($sections['recent_messages']['included']);
        $this->assertSame('budget', $sections['biography']['provenance']['reason'] ?? null);
    }

    public function test_copilot_end_to_end_records_context_metadata_with_fake_chat(): void
    {
        $this->fakeChat()
            ->push('{"topics":["test topic"]}')
            ->push('{"drafts":["one","two","three"]}');

        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/copilot/drafts', [
                'character_id' => $this->npcId,
                'prompt' => 'Мрачно смотрит',
                'scene_id' => $this->sceneId,
            ]);

        $response->assertStatus(200);

        $row = CopilotRequest::query()->findOrFail((int) $response->json('copilot_request_id'));
        $metadata = $row->context_metadata;
        $this->assertIsArray($metadata);
        $this->assertArrayHasKey('sections', $metadata);
        $this->assertArrayHasKey('input_token_budget', $metadata);
        $this->assertTrue($metadata['sections']['system']['included']);
    }

    /**
     * @param  list<string>  $mustStayIncluded
     */
    private function assembleReplyWithOptionalBudgetDrop(
        string $mustDropKey,
        array $mustStayIncluded = [],
    ): ContextBuild {
        $fullEstimate = $this->assembleReply()->metadata['input_token_estimate'];

        for ($budget = $fullEstimate - 1; $budget >= 50; $budget -= 3) {
            try {
                $build = $this->assembleReply($budget);
            } catch (InvalidArgumentException) {
                continue;
            }

            $dropped = $build->metadata['sections'][$mustDropKey];
            if ($dropped['included'] || ($dropped['provenance']['reason'] ?? null) !== 'budget') {
                continue;
            }

            foreach ($mustStayIncluded as $key) {
                if (! $build->metadata['sections'][$key]['included']) {
                    continue 2;
                }
            }

            return $build;
        }

        $this->fail("Could not find a budget that drops {$mustDropKey} without failing required prompt.");
    }

    private function assembleReply(?int $maxInputTokens = null, ?string $prompt = null): ContextBuild
    {
        $request = new ContextRequest(
            npcName: 'Иван',
            prompt: $prompt ?? 'Тестовый промпт для retrieval',
            sceneId: $this->sceneId,
            draftCount: 3,
            storytellerId: $this->storyteller->id,
            gameSessionId: $this->gameSession->id,
            characterId: $this->npcId,
            pass: ContextPass::Reply,
            maxInputTokens: $maxInputTokens,
            maxOutputTokens: 100,
        );

        return app(ContextAssembler::class)->assemble($request);
    }

    private function seedOptionalContextData(): void
    {
        $otherResponse = $this->actingAs($this->storyteller, 'sanctum')
            ->postJson('/api/characters', [
                'canonical_name' => 'Мария',
                'character_type' => 'npc',
            ]);
        $otherResponse->assertStatus(201);
        $otherId = (int) $otherResponse->json('character.id');

        WorldRelation::query()->create([
            'chronicle_id' => $this->chronicle->id,
            'source_type' => 'character',
            'source_id' => $this->npcId,
            'target_type' => 'character',
            'target_id' => $otherId,
            'relation' => WorldRelationKind::Knows,
            'source_of_truth' => 'chronicle',
            'weight' => 1.0,
            'valid_from' => now(),
            'provenance' => [],
        ]);

        for ($i = 0; $i < 3; $i++) {
            $this->createDiaryEntry(
                text: 'NPC заметил странное поведение собеседника. '.str_repeat('Детали. ', 20),
                embeddingSourceText: 'Тестовый промпт для retrieval '.$i,
            );
        }

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson('/api/characters/'.$this->npcId.'/traits', [
                'traits' => [
                    ['key' => 'tone', 'label' => 'Тон', 'value' => str_repeat('Мрачный ', 30)],
                ],
            ])
            ->assertStatus(200);

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson('/api/characters/'.$this->npcId.'/biography', [
                'summary' => str_repeat('Краткая биография. ', 40),
                'motivation' => str_repeat('Мотивация. ', 30),
            ])
            ->assertStatus(200);
    }
}
