<?php

namespace Tests\Feature;

use App\Models\PromptOverride;
use App\Prompt\PromptRepository;
use RuntimeException;
use Tests\TestCase;

class PromptRepositoryTest extends TestCase
{
    public function test_get_returns_factory_file_when_no_override(): void
    {
        $repository = new PromptRepository;

        $content = $repository->get('copilot.topics.system', [
            'npcName' => 'Test NPC',
        ]);

        $this->assertStringContainsString('Test NPC', $content);
        $this->assertStringContainsString('search topics', $content);
    }

    public function test_get_returns_override_from_database_instead_of_file(): void
    {
        PromptOverride::query()->create([
            'key' => 'copilot.topics.system',
            'content' => 'Override for {{npcName}}.',
        ]);

        $repository = new PromptRepository;

        $content = $repository->get('copilot.topics.system', [
            'npcName' => 'Anna',
        ]);

        $this->assertSame('Override for Anna.', $content);
    }

    public function test_get_throws_when_prompt_key_is_unknown(): void
    {
        $repository = new PromptRepository;

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Prompt not found: missing.prompt.key');

        $repository->get('missing.prompt.key');
    }

    public function test_get_prefers_database_after_override_removed_on_fresh_repository(): void
    {
        PromptOverride::query()->create([
            'key' => 'copilot.topics.system',
            'content' => 'Temporary override.',
        ]);

        $override = PromptOverride::query()->where('key', 'copilot.topics.system')->firstOrFail();
        $override->delete();

        $repository = new PromptRepository;

        $content = $repository->get('copilot.topics.system', [
            'npcName' => 'Test NPC',
        ]);

        $this->assertStringContainsString('search topics', $content);
        $this->assertStringNotContainsString('Temporary override.', $content);
    }
}
