<?php

namespace App\Context\Providers;

use App\Context\ContextAssembly;
use App\Context\ContextSection;

interface ContextProvider
{
    public function key(): string;

    public function assemble(ContextAssembly $assembly, int $tokenBudget): ContextSection;
}
