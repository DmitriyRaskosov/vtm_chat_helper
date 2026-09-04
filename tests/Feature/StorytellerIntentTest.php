<?php

namespace Tests\Feature;

use App\Models\ContextSummary;
use App\Models\CopilotRequest;
use App\Models\RagChunk;
use App\Models\Scene;
use App\Models\StorytellerIntentSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StorytellerIntentTest extends TestCase
{
    use RefreshDatabase;

    public function test_intent_summaries_help_copilot_but_are_not_world_memory_or_player_chat(): void
    {
        $this->fakeCopilotAndIntent();
        $storyteller = User::factory()->storyteller()->create();
        $player = User::factory()->create();
        $scene = Scene::query()->active()->firstOrFail();

        Sanctum::actingAs($storyteller);
        $this->postJson('/api/copilot/drafts', [
            'npc_name' => 'Виктория',
            'prompt' => 'SECRET_ST_INTENT держать князя в тени.',
            'scene_id' => $scene->id,
        ])->assertOk();

        $intent = StorytellerIntentSummary::query()->sole();
        $this->assertSame($storyteller->id, $intent->storyteller_id);
        $this->assertSame($scene->game_session_id, $intent->game_session_id);
        $this->assertSame('Держать напряжение Маскарада и не раскрывать секреты князя.', $intent->content);
        $this->assertDatabaseCount('context_summaries', 0);
        $this->assertSame(0, RagChunk::query()->where('content', $intent->content)->count());

        $this->postJson('/api/copilot/drafts', [
            'npc_name' => 'Виктория',
            'prompt' => 'Продолжить ту же линию давления.',
            'scene_id' => $scene->id,
        ])->assertOk();

        Http::assertSent(function (Request $request): bool {
            $system = $request['messages'][0]['content'] ?? '';
            $user = $request['messages'][1]['content'] ?? '';

            return is_string($system)
                && str_contains($system, 'Write exactly')
                && is_string($user)
                && str_contains($user, 'Storyteller intention memory')
                && str_contains($user, 'Держать напряжение Маскарада и не раскрывать секреты князя.');
        });

        $this->assertGreaterThan(1, StorytellerIntentSummary::query()->count());
        $this->assertSame(0, ContextSummary::query()->count());
        $this->assertNull(CopilotRequest::query()->first()?->message);

        Sanctum::actingAs($player);
        $this->getJson('/api/messages?scene_id='.$scene->id)
            ->assertOk()
            ->assertJsonPath('messages', []);
    }

    private function fakeCopilotAndIntent(): void
    {
        Http::fake(function (Request $request) {
            $system = $request['messages'][0]['content'] ?? '';

            if (is_string($system) && str_contains($system, 'compress a storyteller')) {
                return Http::response([
                    'message' => [
                        'content' => json_encode([
                            'narrative' => 'Держать напряжение Маскарада и не раскрывать секреты князя.',
                        ], JSON_UNESCAPED_UNICODE),
                    ],
                ]);
            }

            return Http::response([
                'message' => [
                    'content' => json_encode(['drafts' => ['Один.', 'Два.', 'Три.']], JSON_UNESCAPED_UNICODE),
                ],
            ]);
        });
    }
}
