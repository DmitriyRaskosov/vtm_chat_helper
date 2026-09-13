<?php

namespace Tests\Feature;

use App\Models\GameSession;
use App\Models\Message;
use App\Models\Scene;
use App\Models\User;
use App\Rag\RagIndexer;
use App\Retrieval\RetrievalOrchestrator;
use App\Retrieval\RetrievalScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RetrievalToolsTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_messages_stays_inside_the_current_game_session(): void
    {
        $user = User::factory()->create();
        $activeScene = Scene::query()->active()->firstOrFail();
        $local = Message::factory()->create([
            'user_id' => $user->id,
            'scene_id' => $activeScene->id,
            'body' => 'Секрет элизиума текущей сессии.',
        ]);
        $this->app->make(RagIndexer::class)->indexMessage($local);

        $foreignSession = GameSession::factory()->create();
        $foreignScene = Scene::factory()->create([
            'game_session_id' => $foreignSession->id,
        ]);
        $foreign = Message::factory()->create([
            'user_id' => $user->id,
            'scene_id' => $foreignScene->id,
            'body' => 'Секрет элизиума чужой сессии.',
        ]);
        $this->app->make(RagIndexer::class)->indexMessage($foreign);

        $result = $this->app->make(RetrievalOrchestrator::class)->invoke(
            'search_messages',
            ['query' => 'секрет элизиума'],
            RetrievalScope::fromScene($activeScene),
        );

        $ids = array_column($result['result']->items, 'message_id');
        $this->assertContains($local->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
        $this->assertLessThanOrEqual(5, count($result['result']->items));
    }

    public function test_search_messages_rejects_a_scene_from_another_session(): void
    {
        $activeScene = Scene::query()->active()->firstOrFail();
        $foreignScene = Scene::factory()->create([
            'game_session_id' => GameSession::factory(),
        ]);

        $result = $this->app->make(RetrievalOrchestrator::class)->invoke(
            'search_messages',
            ['query' => 'что угодно', 'scene_id' => $foreignScene->id],
            RetrievalScope::fromScene($activeScene),
        );

        $this->assertFalse($result['result']->ok);
        $this->assertSame([], $result['result']->items);
    }

    public function test_get_message_range_caps_results_and_ignores_other_sessions(): void
    {
        config()->set('copilot.tools.range_limit', 5);
        $user = User::factory()->create();
        $activeScene = Scene::query()->active()->firstOrFail();
        $messages = collect(range(1, 8))->map(fn (int $number): Message => Message::factory()->create([
            'user_id' => $user->id,
            'scene_id' => $activeScene->id,
            'body' => "Реплика {$number}.",
        ]));

        $foreignScene = Scene::factory()->create([
            'game_session_id' => GameSession::factory(),
        ]);
        $foreign = Message::factory()->create([
            'user_id' => $user->id,
            'scene_id' => $foreignScene->id,
            'body' => 'Чужая история целиком.',
        ]);

        $result = $this->app->make(RetrievalOrchestrator::class)->invoke(
            'get_message_range',
            [
                'from_id' => $messages->first()->id,
                'to_id' => $messages->last()->id,
            ],
            RetrievalScope::fromScene($activeScene),
        );

        $this->assertTrue($result['result']->ok);
        $this->assertTrue($result['result']->truncated);
        $this->assertCount(5, $result['result']->items);
        $this->assertSame($messages->first()->id, $result['result']->items[0]['message_id']);
        $this->assertNotContains($foreign->id, array_column($result['result']->items, 'message_id'));
    }

    public function test_unknown_summary_tool_is_rejected(): void
    {
        $activeScene = Scene::query()->active()->firstOrFail();

        $result = $this->app->make(RetrievalOrchestrator::class)->invoke(
            'search_summaries',
            ['query' => 'договор'],
            RetrievalScope::fromScene($activeScene),
        );

        $this->assertFalse($result['result']->ok);
        $this->assertSame([], $result['result']->items);
    }
}
