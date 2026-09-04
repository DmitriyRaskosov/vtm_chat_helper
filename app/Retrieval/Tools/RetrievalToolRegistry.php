<?php

namespace App\Retrieval\Tools;

use App\Retrieval\RetrievalScope;
use App\Retrieval\ToolResult;

class RetrievalToolRegistry
{
    /**
     * @param  list<RetrievalTool>  $tools
     */
    public function __construct(private array $tools) {}

    /**
     * @return list<array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    public function ollamaDefinitions(): array
    {
        return array_map(fn (RetrievalTool $tool): array => [
            'type' => 'function',
            'function' => [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parametersSchema(),
            ],
        ], $this->tools);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function invoke(string $name, array $arguments, RetrievalScope $scope): ToolResult
    {
        foreach ($this->tools as $tool) {
            if ($tool->name() === $name) {
                return $tool->invoke($scope, $arguments);
            }
        }

        return ToolResult::error("Unknown tool: {$name}");
    }

    public function has(string $name): bool
    {
        foreach ($this->tools as $tool) {
            if ($tool->name() === $name) {
                return true;
            }
        }

        return false;
    }
}
