<?php

namespace App\Retrieval;

use App\Context\TokenEstimator;
use App\Llm\ToolCall;
use App\Retrieval\Tools\RetrievalToolRegistry;

class RetrievalOrchestrator
{
    public function __construct(
        private RetrievalToolRegistry $registry,
        private TokenEstimator $estimator,
    ) {}

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{result: ToolResult, json: string, token_estimate: int}
     */
    public function invoke(string $name, array $arguments, RetrievalScope $scope): array
    {
        $result = $this->registry->invoke($name, $arguments, $scope);
        $json = $result->toJson();
        $maxTokens = (int) config('copilot.tools.max_result_tokens', 800);
        $estimate = $this->estimator->estimate($json);

        if ($estimate > $maxTokens) {
            $result = new ToolResult(
                $result->ok,
                array_slice($result->items, 0, 1),
                true,
                $result->error,
            );
            $json = $result->toJson();
            $estimate = $this->estimator->estimate($json);
        }

        return [
            'result' => $result,
            'json' => $json,
            'token_estimate' => $estimate,
        ];
    }

    /**
     * @return array{result: ToolResult, json: string, token_estimate: int}
     */
    public function invokeCall(ToolCall $call, RetrievalScope $scope): array
    {
        return $this->invoke($call->name, $call->arguments, $scope);
    }
}
