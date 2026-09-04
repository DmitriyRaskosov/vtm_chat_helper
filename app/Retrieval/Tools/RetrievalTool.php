<?php

namespace App\Retrieval\Tools;

use App\Retrieval\RetrievalScope;
use App\Retrieval\ToolResult;

interface RetrievalTool
{
    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, mixed>
     */
    public function parametersSchema(): array;

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function invoke(RetrievalScope $scope, array $arguments): ToolResult;
}
