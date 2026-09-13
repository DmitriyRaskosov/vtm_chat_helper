<?php

namespace Tests\Feature;

use App\Character\CharacterLoreKnowledgeService;
use App\Enums\CharacterKnowledgeLevel;
use App\Enums\CharacterMemoryNodeType;
use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Enums\WorldEntityType;
use App\Lore\LoreEntryService;
use App\Lore\LoreIndexer;
use App\Memory\CharacterMemoryService;
use App\Models\Character;
use App\Models\CopilotRequest;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Models\WorldEntity;
use App\Rag\RagIndexer;
use App\World\WorldEntityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CopilotTest extends TestCase
{
    use RefreshDatabase;

    public function test_storyteller_can_generate_drafts(): void
    {
        $this->fakeOllamaDrafts([
            'Первый вариант реплики.',
            'Второй вариант реплики.',
            'Третий вариант реплики.',
        ]);

        $storyteller = User::factory()->storyteller()->create();
        $npc = $this->createNpc();

        Sanctum::actingAs($storyteller);

        $response = $this->postJson('/api/copilot/drafts', [
            'character_id' => $npc->id,
            'prompt' => 'Ответить на вопрос о Маскараде.',
        ])
            ->assertOk()
            ->assertJsonPath('drafts.0', 'Первый вариант реплики.')
            ->assertJsonPath('drafts.1', 'Второй вариант реплики.')
            ->assertJsonPath('drafts.2', 'Третий вариант реплики.');

        $copilotRequest = CopilotRequest::query()->findOrFail(
            $response->json('copilot_request_id'),
        );

        $this->assertSame($storyteller->id, $copilotRequest->storyteller_id);
        $this->assertSame('qwen3:8b', $copilotRequest->model);
        $this->assertSame('context-assembler-v2', $copilotRequest->builder_version);
        $this->assertSame('npc-drafts-v7', $copilotRequest->prompt_version);
        $this->assertSame('reply', $copilotRequest->context_metadata['pass']);
        $this->assertNotEmpty($copilotRequest->context_metadata['topics']);
        $this->assertSame(8000, $copilotRequest->context_metadata['topic_pass']['input_token_budget']);
        $this->assertLessThanOrEqual(
            8000,
            $copilotRequest->context_metadata['topic_pass']['input_token_estimate'],
        );
        $this->assertSame(8, $copilotRequest->context_metadata['topic_pass']['history_limit']);
        $this->assertSame(384, $copilotRequest->context_metadata['topic_pass']['ollama_max_output_tokens']);
        $this->assertSame(
            $copilotRequest->context_metadata['topics'],
            $copilotRequest->context_metadata['search_topics'],
        );
        $this->assertArrayHasKey('sections', $copilotRequest->context_metadata);
        $this->assertSame($npc->id, $copilotRequest->context_metadata['character_id']);
        $this->assertNotNull($copilotRequest->context_metadata['chronicle_id']);
        $this->assertLessThanOrEqual(
            12000,
            $copilotRequest->context_metadata['input_token_estimate'],
        );
        $this->assertSame(16384, $copilotRequest->context_metadata['ollama_context_length']);
        $this->assertSame(3000, $copilotRequest->context_metadata['ollama_max_output_tokens']);
        $this->assertSame([], $copilotRequest->context_metadata['tool_invocations']);
        $this->assertArrayNotHasKey('included_summary_ids', $copilotRequest->context_metadata);
        $this->assertArrayNotHasKey('included_intent_summary_id', $copilotRequest->context_metadata);

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            return ($request['tools'] ?? []) === []
                && (int) ($request['options']['num_predict'] ?? 0) === 384
                && (float) ($request['options']['temperature'] ?? 0) === 0.2
                && ! str_contains((string) ($request['messages'][1]['content'] ?? ''), '## Biography')
                && ! str_contains((string) ($request['messages'][1]['content'] ?? ''), '## World')
                && ! str_contains((string) ($request['messages'][1]['content'] ?? ''), '## Personal memory');
        });
        Http::assertSent(function (Request $request): bool {
            $toolNames = collect($request['tools'] ?? [])
                ->pluck('function.name')
                ->all();

            return $toolNames === ['search_messages', 'get_message_range']
                && $request['options'] === [
                    'num_ctx' => 16384,
                    'num_predict' => 3000,
                ];
        });
    }

    public function test_player_cannot_generate_drafts(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/copilot/drafts', [
            'character_id' => 1,
            'prompt' => 'Тест.',
        ])->assertForbidden();
    }

    public function test_storyteller_can_post_message_as_npc(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create(['name' => 'СТ']));
        $npc = $this->createNpc();

        $this->postJson('/api/messages', [
            'body' => 'Добрый вечер, смертные.',
            'character_id' => $npc->id,
        ])
            ->assertCreated()
            ->assertJsonPath('message.author', 'Виктория')
            ->assertJsonPath('message.mine', false)
            ->assertJsonPath('message.npc_name', 'Виктория');

        $this->assertDatabaseHas('messages', [
            'body' => 'Добрый вечер, смертные.',
            'npc_name' => 'Виктория',
        ]);
    }

    public function test_storyteller_can_link_an_edited_draft_to_the_posted_message(): void
    {
        $this->fakeOllamaDrafts(['Один.', 'Два.', 'Три.']);
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->firstOrFail();
        $npc = $this->createNpc($scene);

        Sanctum::actingAs($storyteller);

        $copilotRequestId = $this->postJson('/api/copilot/drafts', [
            'character_id' => $npc->id,
            'prompt' => 'Ответить с угрозой.',
            'scene_id' => $scene->id,
        ])->assertOk()->json('copilot_request_id');

        $this->postJson('/api/messages', [
            'body' => 'Отредактированный второй вариант.',
            'character_id' => $npc->id,
            'scene_id' => $scene->id,
            'copilot_request_id' => $copilotRequestId,
            'copilot_draft_index' => 1,
        ])->assertCreated();

        $this->assertDatabaseHas('messages', [
            'body' => 'Отредактированный второй вариант.',
            'copilot_request_id' => $copilotRequestId,
        ]);
        $this->assertDatabaseHas('copilot_requests', [
            'id' => $copilotRequestId,
            'selected_draft_index' => 1,
        ]);
    }

    public function test_copilot_request_cannot_be_reused(): void
    {
        $this->fakeOllamaDrafts(['Один.', 'Два.', 'Три.']);
        $storyteller = User::factory()->storyteller()->create();
        $npc = $this->createNpc();

        Sanctum::actingAs($storyteller);

        $copilotRequestId = $this->postJson('/api/copilot/drafts', [
            'character_id' => $npc->id,
            'prompt' => 'Тест.',
        ])->assertOk()->json('copilot_request_id');

        $payload = [
            'body' => 'Один.',
            'character_id' => $npc->id,
            'copilot_request_id' => $copilotRequestId,
            'copilot_draft_index' => 0,
        ];

        $this->postJson('/api/messages', $payload)->assertCreated();
        $this->postJson('/api/messages', $payload)->assertConflict();
    }

    public function test_copilot_request_cannot_be_used_by_another_storyteller(): void
    {
        $this->fakeOllamaDrafts(['Один.', 'Два.', 'Три.']);
        $owner = User::factory()->storyteller()->create();
        $otherStoryteller = User::factory()->storyteller()->create();
        $npc = $this->createNpc();

        Sanctum::actingAs($owner);
        $copilotRequestId = $this->postJson('/api/copilot/drafts', [
            'character_id' => $npc->id,
            'prompt' => 'Тест.',
        ])->assertOk()->json('copilot_request_id');

        Sanctum::actingAs($otherStoryteller);
        $this->postJson('/api/messages', [
            'body' => 'Один.',
            'character_id' => $npc->id,
            'copilot_request_id' => $copilotRequestId,
            'copilot_draft_index' => 0,
        ])->assertForbidden();

        $this->assertDatabaseMissing('messages', [
            'copilot_request_id' => $copilotRequestId,
        ]);
    }

    public function test_context_builder_uses_recent_scene_messages_without_passive_rag(): void
    {
        $this->fakeOllamaDrafts(['Один.', 'Два.', 'Три.']);
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->firstOrFail();
        $npc = $this->createNpc($scene);
        $message = Message::factory()->create([
            'user_id' => $storyteller->id,
            'scene_id' => $scene->id,
            'body' => 'Уникальная фраза о князе города.',
        ]);
        $this->app->make(RagIndexer::class)->indexMessage($message);

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/copilot/drafts', [
            'character_id' => $npc->id,
            'prompt' => 'Что известно о князе?',
            'scene_id' => $scene->id,
        ])->assertOk();

        Http::assertSent(function (Request $request): bool {
            $content = $request['messages'][1]['content'];

            return substr_count($content, 'Уникальная фраза о князе города.') === 1
                && str_contains($content, '## Scene speech')
                && str_contains($content, '[speech]')
                && ! str_contains($content, 'Relevant past context')
                && ! str_contains($content, 'Relevant memory summaries')
                && ! str_contains($content, 'Storyteller intention memory');
        });

        $metadata = CopilotRequest::query()->firstOrFail()->context_metadata;
        $this->assertSame([$message->id], $metadata['included_raw_message_ids']);
        $this->assertArrayNotHasKey('included_rag_chunk_ids', $metadata);
    }

    public function test_context_builder_keeps_the_newest_history_within_its_budget(): void
    {
        config()->set('context.copilot.max_input_tokens', 500);
        $this->fakeOllamaDrafts(['Один.', 'Два.', 'Три.']);
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->firstOrFail();
        $npc = $this->createNpc($scene);
        Message::factory()->create([
            'user_id' => $storyteller->id,
            'scene_id' => $scene->id,
            'body' => str_repeat('Старое длинное сообщение. ', 100),
        ]);
        $newest = Message::factory()->create([
            'user_id' => $storyteller->id,
            'scene_id' => $scene->id,
            'body' => 'Самая свежая реплика.',
        ]);

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/copilot/drafts', [
            'character_id' => $npc->id,
            'prompt' => 'Ответить.',
            'scene_id' => $scene->id,
        ])->assertOk();

        $metadata = CopilotRequest::query()->firstOrFail()->context_metadata;
        $this->assertLessThanOrEqual(500, $metadata['input_token_estimate']);
        $this->assertSame([$newest->id], $metadata['included_raw_message_ids']);
        $this->assertSame(1, $metadata['excluded_raw_message_count']);
    }

    public function test_copilot_tool_loop_records_scoped_retrieval(): void
    {
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->firstOrFail();
        $npc = $this->createNpc($scene);
        $first = Message::factory()->create([
            'user_id' => $storyteller->id,
            'scene_id' => $scene->id,
            'body' => 'Первая реплика диапазона.',
        ]);
        $second = Message::factory()->create([
            'user_id' => $storyteller->id,
            'scene_id' => $scene->id,
            'body' => 'Вторая реплика диапазона.',
        ]);

        Http::fake([
            config('ollama.url').'/api/chat' => Http::sequence()
                ->push([
                    'message' => [
                        'content' => json_encode(['topics' => ['предыдущий обмен']], JSON_UNESCAPED_UNICODE),
                    ],
                ])
                ->push([
                    'message' => [
                        'content' => '',
                        'tool_calls' => [[
                            'function' => [
                                'name' => 'get_message_range',
                                'arguments' => [
                                    'from_id' => $first->id,
                                    'to_id' => $second->id,
                                ],
                            ],
                        ]],
                    ],
                ])
                ->push([
                    'message' => [
                        'content' => json_encode(['drafts' => ['Один.', 'Два.', 'Три.']], JSON_UNESCAPED_UNICODE),
                    ],
                ]),
        ]);

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/copilot/drafts', [
            'character_id' => $npc->id,
            'prompt' => 'Опереться на предыдущий обмен.',
            'scene_id' => $scene->id,
        ])->assertOk();

        $metadata = CopilotRequest::query()->firstOrFail()->context_metadata;
        $this->assertSame(['предыдущий обмен'], $metadata['topics']);
        $this->assertSame(1, $metadata['tool_iterations']);
        $this->assertSame('get_message_range', $metadata['tool_invocations'][0]['name']);
        $this->assertTrue($metadata['tool_invocations'][0]['ok']);
        $this->assertSame(2, $metadata['tool_invocations'][0]['count']);
        $this->assertFalse($metadata['tool_invocations'][0]['truncated']);
    }

    public function test_copilot_records_memory_and_world_graph_provenance_end_to_end(): void
    {
        $this->fakeOllamaDrafts(['Один.', 'Два.', 'Три.']);
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->with('gameSession.chronicle')->firstOrFail();
        $npcEntity = $this->createNpc($scene);
        $npc = Character::query()->findOrFail($npcEntity->id);
        $prompt = 'Виктория помнит тайную встречу в Элизиуме. Под Элизиумом скрыт запечатанный архив.';
        $memory = $this->app->make(CharacterMemoryService::class)->remember(
            $npc,
            'Виктория помнит тайную встречу в Элизиуме.',
            CharacterMemoryNodeType::Event,
            aliases: [$prompt],
        );
        $lore = $this->app->make(LoreEntryService::class)->publish(
            $scene->gameSession->chronicle,
            [
                'title' => 'Тайна Элизиума',
                'canonical_text' => 'Под Элизиумом скрыт запечатанный архив.',
                'status' => LoreEntryStatus::Approved,
                'visibility' => LoreVisibility::Public,
            ],
            'E2E provenance fixture.',
        );
        $this->app->make(LoreEntryService::class)->attachEntity($lore, $npcEntity);
        $this->app->make(LoreIndexer::class)->rebuildApproved($lore);
        $this->app->make(CharacterLoreKnowledgeService::class)->grant(
            $npc,
            $lore,
            CharacterKnowledgeLevel::Known,
        );

        Sanctum::actingAs($storyteller);
        $response = $this->postJson('/api/copilot/drafts', [
            'character_id' => $npc->id,
            'prompt' => $prompt,
            'scene_id' => $scene->id,
        ])->assertOk();

        $metadata = CopilotRequest::query()
            ->findOrFail($response->json('copilot_request_id'))
            ->context_metadata;

        $this->assertTrue($metadata['sections']['memory_graph']['included']);
        $this->assertContains(
            $memory->id,
            $metadata['sections']['memory_graph']['provenance']['node_ids'],
        );
        $this->assertTrue($metadata['sections']['world_lore']['included']);
        $this->assertContains(
            $lore->id,
            $metadata['sections']['world_lore']['provenance']['lore_entry_ids'],
        );
        $this->assertContains($prompt, $metadata['topics']);
    }

    public function test_npc_name_input_is_rejected(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/messages', [
            'body' => 'Притворяюсь НПС.',
            'npc_name' => 'Виктория',
        ])->assertUnprocessable()->assertJsonValidationErrors('npc_name');
    }

    /**
     * @param  list<string>  $drafts
     */
    private function fakeOllamaDrafts(array $drafts): void
    {
        $payload = json_encode(['drafts' => $drafts], JSON_UNESCAPED_UNICODE);

        Http::fake(function (Request $request) use ($payload) {
            $predict = (int) ($request['options']['num_predict'] ?? 3000);
            if ($predict <= 512) {
                return Http::response([
                    'message' => [
                        'content' => json_encode(
                            ['topics' => $this->topicsFromOllamaRequest($request)],
                            JSON_UNESCAPED_UNICODE,
                        ),
                    ],
                ]);
            }

            return Http::response([
                'message' => ['content' => $payload],
            ]);
        });
    }

    /**
     * @return list<string>
     */
    private function topicsFromOllamaRequest(Request $request): array
    {
        $content = $request['messages'][1]['content'] ?? '';
        $prompt = '';
        if (is_string($content) && preg_match('/## Storyteller prompt\n(.+?)(?:\n\n## |\n\nExtract |\z)/s', $content, $matches)) {
            $prompt = trim($matches[1]);
        }

        return $prompt !== '' ? [$prompt] : ['conversation'];
    }

    private function createNpc(?Scene $scene = null): WorldEntity
    {
        $scene ??= Scene::query()->active()->firstOrFail();
        $scene->loadMissing('gameSession.chronicle');

        return $this->app->make(WorldEntityService::class)->create(
            $scene->gameSession->chronicle,
            WorldEntityType::Character,
            'Виктория',
        );
    }
}
