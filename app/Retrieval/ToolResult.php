<?php

namespace App\Retrieval;

final readonly class ToolResult
{
    /**
     * @param  list<array<string, mixed>>  $items
     */
    public function __construct(
        public bool $ok,
        public array $items,
        public bool $truncated = false,
        public ?string $error = null,
    ) {}

    public static function error(string $message): self
    {
        return new self(false, [], false, $message);
    }

    public function toJson(): string
    {
        $payload = [
            'ok' => $this->ok,
            'truncated' => $this->truncated,
            'count' => count($this->items),
            'items' => $this->items,
        ];

        if ($this->error !== null) {
            $payload['error'] = $this->error;
        }

        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
