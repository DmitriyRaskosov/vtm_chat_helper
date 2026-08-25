<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

        Sanctum::actingAs(User::factory()->storyteller()->create());

        $this->postJson('/api/copilot/drafts', [
            'npc_name' => 'Виктория',
            'prompt' => 'Ответить на вопрос о Маскараде.',
        ])
            ->assertOk()
            ->assertJsonPath('drafts.0', 'Первый вариант реплики.')
            ->assertJsonPath('drafts.1', 'Второй вариант реплики.')
            ->assertJsonPath('drafts.2', 'Третий вариант реплики.');
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
