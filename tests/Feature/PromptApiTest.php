<?php

namespace Tests\Feature;

use App\Enums\ChronicleRole;
use App\Models\Chronicle;
use App\Models\PromptOverride;
use App\Models\User;
use Database\Seeders\CanonClanSeeder;
use Database\Seeders\CanonSectSeeder;
use Tests\Support\JoinsChronicle;
use Tests\TestCase;

class PromptApiTest extends TestCase
{
    use JoinsChronicle;

    private User $storyteller;

    private User $player;

    private Chronicle $chronicle;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CanonSectSeeder::class);
        $this->seed(CanonClanSeeder::class);

        $this->storyteller = User::factory()->create();
        $this->player = User::factory()->create();

        $this->chronicle = Chronicle::factory()->create([
            'created_by' => $this->storyteller->id,
        ]);
        $this->joinChronicle($this->storyteller, $this->chronicle, ChronicleRole::Storyteller);
        $this->joinChronicle($this->player, $this->chronicle, ChronicleRole::Player);
    }

    public function test_player_cannot_list_workshop_prompts(): void
    {
        $this->actingAs($this->player, 'sanctum')
            ->getJson('/api/workshop/prompts')
            ->assertStatus(403);
    }

    public function test_storyteller_lists_prompts_with_content_and_metadata(): void
    {
        $response = $this->actingAs($this->storyteller, 'sanctum')
            ->getJson('/api/workshop/prompts')
            ->assertStatus(200);

        $prompts = $response->json('prompts');
        $this->assertIsArray($prompts);
        $this->assertNotEmpty($prompts);

        $topics = collect($prompts)->firstWhere('key', 'copilot.topics.system');
        $this->assertNotNull($topics);
        $this->assertSame('Copilot', $topics['group']);
        $this->assertNotEmpty($topics['title']);
        $this->assertNotEmpty($topics['description']);
        $this->assertIsArray($topics['variables']);
        $this->assertArrayHasKey('content', $topics);
        $this->assertStringContainsString('search topics', $topics['content']);
        $this->assertFalse($topics['is_overridden']);
    }

    public function test_storyteller_put_saves_override_and_index_reflects_it(): void
    {
        $custom = 'Custom topics prompt for {{npcName}}.';

        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson('/api/workshop/prompts/copilot.topics.system', [
                'content' => $custom,
            ])
            ->assertStatus(200)
            ->assertJsonPath('prompt.content', $custom)
            ->assertJsonPath('prompt.is_overridden', true);

        $this->assertDatabaseHas('prompt_overrides', [
            'key' => 'copilot.topics.system',
            'content' => $custom,
        ]);

        $listed = collect(
            $this->actingAs($this->storyteller, 'sanctum')->getJson('/api/workshop/prompts')->json('prompts')
        )->firstWhere('key', 'copilot.topics.system');

        $this->assertSame($custom, $listed['content']);
        $this->assertTrue($listed['is_overridden']);
    }

    public function test_storyteller_delete_removes_override(): void
    {
        PromptOverride::query()->create([
            'key' => 'copilot.topics.system',
            'content' => 'Temporary override.',
        ]);

        $this->actingAs($this->storyteller, 'sanctum')
            ->deleteJson('/api/workshop/prompts/copilot.topics.system')
            ->assertStatus(200)
            ->assertJsonPath('deleted', true);

        $this->assertDatabaseMissing('prompt_overrides', [
            'key' => 'copilot.topics.system',
        ]);

        $listed = collect(
            $this->actingAs($this->storyteller, 'sanctum')->getJson('/api/workshop/prompts')->json('prompts')
        )->firstWhere('key', 'copilot.topics.system');

        $this->assertFalse($listed['is_overridden']);
        $this->assertStringContainsString('search topics', $listed['content']);
    }

    public function test_put_rejects_empty_content(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson('/api/workshop/prompts/copilot.topics.system', [
                'content' => '   ',
            ])
            ->assertStatus(422);
    }

    public function test_put_rejects_unknown_prompt_key(): void
    {
        $this->actingAs($this->storyteller, 'sanctum')
            ->putJson('/api/workshop/prompts/unknown.prompt.key', [
                'content' => 'Should not be stored.',
            ])
            ->assertStatus(404);

        $this->assertDatabaseCount('prompt_overrides', 0);
    }

    public function test_player_cannot_put_prompt_override(): void
    {
        $this->actingAs($this->player, 'sanctum')
            ->putJson('/api/workshop/prompts/copilot.topics.system', [
                'content' => 'Blocked.',
            ])
            ->assertStatus(403);
    }
}
