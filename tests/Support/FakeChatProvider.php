<?php

namespace Tests\Support;

use App\Llm\ChatProvider;
use App\Llm\ChatTurn;
use RuntimeException;

final class FakeChatProvider implements ChatProvider
{
    /** @var list<string|array{content?: string}> */
    private array $queue = [];

    /** @var list<array{method: string, messages: list<array<string, mixed>>, options: array<string, int|float|string|bool>}> */
    private array $recorded = [];

    public function push(string|array $response): self
    {
        $this->queue[] = $response;

        return $this;
    }

    /**
     * @return list<array{method: string, messages: list<array<string, mixed>>, options: array<string, int|float|string|bool>}>
     */
    public function requests(): array
    {
        return $this->recorded;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, int|float|string|bool>  $options
     */
    public function chat(array $messages, array $options = []): string
    {
        $turn = $this->chatTurn($messages, $options);
        if ($turn->content === '') {
            throw new RuntimeException('FakeChatProvider returned empty content.');
        }

        return $turn->content;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  array<string, int|float|string|bool>  $options
     */
    public function chatTurn(array $messages, array $options = []): ChatTurn
    {
        $this->recorded[] = [
            'method' => 'chatTurn',
            'messages' => $messages,
            'options' => $options,
        ];

        if ($this->queue === []) {
            throw new RuntimeException('FakeChatProvider queue is empty.');
        }

        $item = array_shift($this->queue);
        $content = is_string($item) ? $item : (string) ($item['content'] ?? '');

        return new ChatTurn($content);
    }

    public function complete(string $prompt): string
    {
        $this->recorded[] = [
            'method' => 'complete',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'options' => [],
        ];

        return $this->chat([
            ['role' => 'user', 'content' => $prompt],
        ]);
    }
}
