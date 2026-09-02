<?php

namespace Tests\Feature;

use App\Context\ContextBuilder;
use App\Context\SummaryManager;
use App\Enums\ContextSummaryLevel;
use App\Enums\ContextSummarySourceType;
use App\Llm\ChatProvider;
use App\Models\ContextSummary;
use App\Models\Message;
use App\Models\Scene;
use App\Models\SceneContextState;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class ContextSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_l0_is_created_only_when_a_complete_window_is_ready(): void
    {
        $chat = $this->fakeChat();
        $scene = Scene::query()->active()->firstOrFail();
        $user = User::factory()->create();
        $this->messages($scene, $user, 49);

        $this->app->make(SummaryManager::class)->summarizeAvailableL0($scene->id);
        $this->assertDatabaseCount('context_summaries', 0);

        $last = Message::factory()->create(['scene_id' => $scene->id, 'user_id' => $user->id]);
        $this->app->make(SummaryManager::class)->summarizeAvailableL0($scene->id);

        $summary = ContextSummary::query()->with('sources')->sole();
        $this->assertSame(ContextSummaryLevel::L0, $summary->level);
        $this->assertSame(50, $summary->sources->count());
        $this->assertSame(ContextSummarySourceType::Message, $summary->sources->first()->source_type);
        $this->assertSame($last->id, $summary->last_message_id);
        $this->assertSame($last->id, SceneContextState::query()->sole()->last_summarized_message_id);
        $this->assertCount(1, $chat->summaryCalls);
        $this->assertSame([
            'num_ctx' => 24576,
            'num_predict' => 3000,
        ], $chat->summaryCalls[0]['options']);
        $this->assertDatabaseHas('rag_chunks', [
            'source_type' => 'summary',
            'source_id' => (string) $summary->id,
        ]);
    }

    public function test_repeated_l0_runs_are_idempotent(): void
    {
        $chat = $this->fakeChat();
        $scene = Scene::query()->active()->firstOrFail();
        $this->messages($scene, User::factory()->create(), 50);
        $manager = $this->app->make(SummaryManager::class);

        $manager->summarizeAvailableL0($scene->id);
        $manager->summarizeAvailableL0($scene->id);

        $this->assertDatabaseCount('context_summaries', 1);
        $this->assertDatabaseCount('context_summary_sources', 50);
        $this->assertCount(1, $chat->summaryCalls);
    }

    public function test_failed_generation_does_not_advance_the_l0_cursor(): void
    {
        $chat = $this->fakeChat();
        $chat->failSummaries = true;
        $scene = Scene::query()->active()->firstOrFail();
        $this->messages($scene, User::factory()->create(), 50);

        try {
            $this->app->make(SummaryManager::class)->summarizeAvailableL0($scene->id);
            $this->fail('Summary generation should fail.');
        } catch (RuntimeException) {
            //
        }

        $this->assertDatabaseCount('context_summaries', 0);
        $this->assertNull(SceneContextState::query()->sole()->last_summarized_message_id);
    }

    public function test_token_overflow_and_oversized_messages_form_whole_l0_windows(): void
    {
        $this->fakeChat();
        $scene = Scene::query()->active()->firstOrFail();
        $user = User::factory()->create();
        $first = Message::factory()->create(['scene_id' => $scene->id, 'user_id' => $user->id]);
        $second = Message::factory()->create(['scene_id' => $scene->id, 'user_id' => $user->id]);
        DB::table('messages')->where('id', $first->id)->update(['token_estimate' => 14999]);
        DB::table('messages')->where('id', $second->id)->update(['token_estimate' => 2]);

        $manager = $this->app->make(SummaryManager::class);
        $manager->summarizeAvailableL0($scene->id);

        $firstSummary = ContextSummary::query()->with('sources')->sole();
        $this->assertSame([$first->id], $firstSummary->sources->pluck('source_id')->all());

        $manager->summarizeAvailableL0($scene->id, true);
        $this->assertDatabaseCount('context_summaries', 2);

        $oversized = Message::factory()->create(['scene_id' => $scene->id, 'user_id' => $user->id]);
        DB::table('messages')->where('id', $oversized->id)->update(['token_estimate' => 15001]);
        $manager->summarizeAvailableL0($scene->id);

        $oversizedSummary = ContextSummary::query()
            ->where('first_message_id', $oversized->id)
            ->sole();
        $this->assertTrue($oversizedSummary->metadata['oversized']);
    }

    public function test_l1_final_scene_and_session_summaries_preserve_provenance(): void
    {
        $this->fakeChat();
        $scene = Scene::query()->active()->firstOrFail();
        $this->messages($scene, User::factory()->create(), 1000);
        $manager = $this->app->make(SummaryManager::class);

        $manager->summarizeAvailableL0($scene->id);
        $final = $manager->finalizeScene($scene->id);

        $this->assertNotNull($final);
        $this->assertSame(ContextSummaryLevel::SceneFinal, $final->level);
        $this->assertSame(20, ContextSummary::query()->where('level', ContextSummaryLevel::L0)->count());
        $this->assertSame(4, ContextSummary::query()->where('level', ContextSummaryLevel::L1)->count());
        $this->assertSame(1, ContextSummary::query()->where('level', ContextSummaryLevel::Session)->count());

        $l1Summaries = ContextSummary::query()
            ->with('sources')
            ->where('level', ContextSummaryLevel::L1)
            ->get();
        $this->assertTrue($l1Summaries->every(
            fn (ContextSummary $summary): bool => $summary->sources->count() === 5
                && $summary->sources->every(
                    fn ($source): bool => $source->source_type === ContextSummarySourceType::Summary,
                ),
        ));

        $final->load('sources');
        $this->assertSame(4, $final->sources->count());
        $this->assertSame(1, ContextSummary::query()->where('level', ContextSummaryLevel::SceneFinal)->count());

        $manager->finalizeScene($scene->id);
        $this->assertSame(1, ContextSummary::query()->where('level', ContextSummaryLevel::SceneFinal)->count());
        $this->assertSame(1, ContextSummary::query()->where('level', ContextSummaryLevel::Session)->count());
    }

    public function test_context_builder_uses_old_summary_and_keeps_raw_hot_tail(): void
    {
        config()->set('context.l1.summary_count', 5);
        $this->fakeChat();
        $scene = Scene::query()->active()->firstOrFail();
        $user = User::factory()->create(['name' => 'Анна']);
        $this->messages($scene, $user, 100);
        $this->app->make(SummaryManager::class)->summarizeAvailableL0($scene->id);

        $build = $this->app->make(ContextBuilder::class)->build(
            'Виктория',
            'Ответить на последнюю реплику.',
            $scene->id,
            3,
        );
        $userPrompt = $build->messages[1]['content'];

        $this->assertStringContainsString('Relevant memory summaries:', $userPrompt);
        $this->assertStringContainsString('Recent chat:', $userPrompt);
        $this->assertCount(1, $build->metadata['included_summary_ids']);
        $this->assertLessThanOrEqual(12000, $build->metadata['input_token_estimate']);
    }

    public function test_session_summary_gets_a_new_immutable_version_for_each_finalized_scene(): void
    {
        $this->fakeChat();
        $firstScene = Scene::query()->active()->firstOrFail();
        $session = $firstScene->gameSession;
        $user = User::factory()->create();
        $this->messages($firstScene, $user, 1);
        $manager = $this->app->make(SummaryManager::class);
        $manager->finalizeScene($firstScene->id);

        $secondScene = $session->scenes()->create([
            'position' => 2,
            'title' => 'Вторая сцена',
        ]);
        $this->messages($secondScene, $user, 1);
        $manager->finalizeScene($secondScene->id);

        $sessionSummaries = ContextSummary::query()
            ->with('sources')
            ->where('level', ContextSummaryLevel::Session)
            ->orderBy('id')
            ->get();

        $this->assertSame(2, $sessionSummaries->count());
        $this->assertSame(1, $sessionSummaries->first()->sources->count());
        $this->assertSame(2, $sessionSummaries->last()->sources->count());
        $this->assertNotSame(
            $sessionSummaries->first()->source_hash,
            $sessionSummaries->last()->source_hash,
        );
    }

    private function fakeChat(): RecordingSummaryChatProvider
    {
        $chat = new RecordingSummaryChatProvider;
        $this->app->instance(ChatProvider::class, $chat);

        return $chat;
    }

    private function messages(Scene $scene, User $user, int $count): void
    {
        foreach (range(1, $count) as $number) {
            Message::factory()->create([
                'scene_id' => $scene->id,
                'user_id' => $user->id,
                'body' => "Событие {$number}.",
            ]);
        }
    }
}

class RecordingSummaryChatProvider implements ChatProvider
{
    public bool $failSummaries = false;

    /** @var list<array<string, mixed>> */
    public array $summaryCalls = [];

    public function chat(array $messages, array $options = []): string
    {
        if (str_contains($messages[0]['content'], 'You summarize')) {
            if ($this->failSummaries) {
                throw new RuntimeException('Ollama failed.');
            }

            $this->summaryCalls[] = ['messages' => $messages, 'options' => $options];

            return json_encode([
                'narrative' => 'Краткое последовательное изложение событий.',
                'participants' => ['Анна'],
                'locations' => [],
                'key_events' => ['Произошли события сцены.'],
                'facts' => [],
                'decisions' => [],
                'unresolved_threads' => [],
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        }

        return json_encode(['drafts' => ['Один.', 'Два.', 'Три.']], JSON_THROW_ON_ERROR);
    }

    public function complete(string $prompt): string
    {
        return $this->chat([['role' => 'user', 'content' => $prompt]]);
    }
}
