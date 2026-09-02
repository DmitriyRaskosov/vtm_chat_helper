<?php

namespace Tests\Feature;

use App\Models\CopilotRequest;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Rag\RagIndexer;
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

        Sanctum::actingAs($storyteller);

        $response = $this->postJson('/api/copilot/drafts', [
            'npc_name' => 'Виктория',
            'prompt' => 'Ответить на вопрос о Маскараде.',
        ])
            ->assertOk()
            ->assertJsonPath('copilot_request_id', 1)
            ->assertJsonPath('drafts.0', 'Первый вариант реплики.')
            ->assertJsonPath('drafts.1', 'Второй вариант реплики.')
            ->assertJsonPath('drafts.2', 'Третий вариант реплики.');

        $copilotRequest = CopilotRequest::query()->findOrFail(
            $response->json('copilot_request_id'),
        );

        $this->assertSame($storyteller->id, $copilotRequest->storyteller_id);
        $this->assertSame('qwen3:8b', $copilotRequest->model);
        $this->assertSame('context-builder-v2', $copilotRequest->builder_version);
        $this->assertSame('npc-drafts-v3', $copilotRequest->prompt_version);
        $this->assertLessThanOrEqual(
            12000,
            $copilotRequest->context_metadata['input_token_estimate'],
        );
        $this->assertSame(16384, $copilotRequest->context_metadata['ollama_context_length']);
        $this->assertSame(3000, $copilotRequest->context_metadata['ollama_max_output_tokens']);

        Http::assertSent(fn (Request $request): bool => $request['options'] === [
            'num_ctx' => 16384,
            'num_predict' => 3000,
        ]);
    }

    public function test_player_cannot_generate_drafts(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/copilot/drafts', [
            'npc_name' => 'Виктория',
            'prompt' => 'Тест.',
        ])->assertForbidden();
    }

    public function test_storyteller_can_post_message_as_npc(): void
    {
        Sanctum::actingAs(User::factory()->storyteller()->create(['name' => 'СТ']));

        $this->postJson('/api/messages', [
            'body' => 'Добрый вечер, смертные.',
            'npc_name' => 'Виктория',
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

        Sanctum::actingAs($storyteller);

        $copilotRequestId = $this->postJson('/api/copilot/drafts', [
            'npc_name' => 'Виктория',
            'prompt' => 'Ответить с угрозой.',
            'scene_id' => $scene->id,
        ])->assertOk()->json('copilot_request_id');

        $this->postJson('/api/messages', [
            'body' => 'Отредактированный второй вариант.',
            'npc_name' => 'Виктория',
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

        Sanctum::actingAs($storyteller);

        $copilotRequestId = $this->postJson('/api/copilot/drafts', [
            'npc_name' => 'Виктория',
            'prompt' => 'Тест.',
        ])->assertOk()->json('copilot_request_id');

        $payload = [
            'body' => 'Один.',
            'npc_name' => 'Виктория',
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

        Sanctum::actingAs($owner);
        $copilotRequestId = $this->postJson('/api/copilot/drafts', [
            'npc_name' => 'Виктория',
            'prompt' => 'Тест.',
        ])->assertOk()->json('copilot_request_id');

        Sanctum::actingAs($otherStoryteller);
        $this->postJson('/api/messages', [
            'body' => 'Один.',
            'npc_name' => 'Виктория',
            'copilot_request_id' => $copilotRequestId,
            'copilot_draft_index' => 0,
        ])->assertForbidden();

        $this->assertDatabaseMissing('messages', [
            'copilot_request_id' => $copilotRequestId,
        ]);
    }

    public function test_context_builder_deduplicates_recent_messages_from_rag(): void
    {
        $this->fakeOllamaDrafts(['Один.', 'Два.', 'Три.']);
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->firstOrFail();
        $message = Message::factory()->create([
            'user_id' => $storyteller->id,
            'scene_id' => $scene->id,
            'body' => 'Уникальная фраза о князе города.',
        ]);
        $this->app->make(RagIndexer::class)->indexMessage($message);

        Sanctum::actingAs($storyteller);

        $this->postJson('/api/copilot/drafts', [
            'npc_name' => 'Виктория',
            'prompt' => 'Что известно о князе?',
            'scene_id' => $scene->id,
        ])->assertOk();

        Http::assertSent(function (Request $request): bool {
            $content = $request['messages'][1]['content'];

            return substr_count($content, 'Уникальная фраза о князе города.') === 1;
        });

        $metadata = CopilotRequest::query()->firstOrFail()->context_metadata;
        $this->assertSame([$message->id], $metadata['included_raw_message_ids']);
        $this->assertSame([], $metadata['included_rag_chunk_ids']);
    }

    public function test_context_builder_keeps_the_newest_history_within_its_budget(): void
    {
        config()->set('context.copilot.max_input_tokens', 500);
        $this->fakeOllamaDrafts(['Один.', 'Два.', 'Три.']);
        $storyteller = User::factory()->storyteller()->create();
        $scene = Scene::query()->active()->firstOrFail();
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
            'npc_name' => 'Виктория',
            'prompt' => 'Ответить.',
            'scene_id' => $scene->id,
        ])->assertOk();

        $metadata = CopilotRequest::query()->firstOrFail()->context_metadata;
        $this->assertLessThanOrEqual(500, $metadata['input_token_estimate']);
        $this->assertSame([$newest->id], $metadata['included_raw_message_ids']);
        $this->assertSame(1, $metadata['excluded_raw_message_count']);
    }

    public function test_player_cannot_post_message_as_npc(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/messages', [
            'body' => 'Притворяюсь НПС.',
            'npc_name' => 'Виктория',
        ])->assertForbidden();
    }

    /**
     * @param  list<string>  $drafts
     */
    private function fakeOllamaDrafts(array $drafts): void
    {
        $payload = json_encode(['drafts' => $drafts], JSON_UNESCAPED_UNICODE);

        Http::fake([
            config('ollama.url').'/api/chat' => Http::response([
                'message' => ['content' => $payload],
            ]),
        ]);
    }
}
