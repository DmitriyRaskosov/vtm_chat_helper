<?php

namespace Tests\Feature;

use App\Llm\Exceptions\LlmResponseInvalidException;
use App\Llm\NpcCopilotService;
use Tests\Support\CreatesCopilotScene;
use Tests\TestCase;

class NpcCopilotRetryTest extends TestCase
{
    use CreatesCopilotScene;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCopilotScene();
    }

    public function test_drafts_retries_once_when_finish_reason_is_length(): void
    {
        $fake = $this->fakeChat()
            ->push('{"topics":["разговор"]}')
            ->push([
                'content' => '{"drafts":["one","two"',
                'finishReason' => 'length',
            ])
            ->push('{"drafts":["alpha","beta","gamma"]}');

        $result = $this->app->make(NpcCopilotService::class)->drafts(
            'Иван',
            'Синопсис',
            $this->sceneId,
            $this->storyteller->id,
            $this->npcId,
        );

        $this->assertSame(['alpha', 'beta', 'gamma'], $result->drafts);
        $this->assertCount(3, $fake->requests());

        $retryMessages = $fake->requests()[2]['messages'];
        $lastMessage = $retryMessages[array_key_last($retryMessages)];
        $this->assertSame('user', $lastMessage['role']);
        $this->assertStringContainsString('too long', $lastMessage['content']);
        $this->assertStringContainsString('valid JSON', $lastMessage['content']);
    }

    public function test_drafts_throws_when_retry_also_returns_length(): void
    {
        $this->fakeChat()
            ->push('{"topics":["разговор"]}')
            ->push([
                'content' => '{"drafts":["one","two"',
                'finishReason' => 'length',
            ])
            ->push([
                'content' => '{"drafts":["x","y"',
                'finishReason' => 'length',
            ]);

        $this->expectException(LlmResponseInvalidException::class);

        $this->app->make(NpcCopilotService::class)->drafts(
            'Иван',
            'Синопсис',
            $this->sceneId,
            $this->storyteller->id,
            $this->npcId,
        );
    }
}
