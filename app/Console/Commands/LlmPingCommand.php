<?php

namespace App\Console\Commands;

use App\Llm\ChatProvider;
use Illuminate\Console\Command;

class LlmPingCommand extends Command
{
    protected $signature = 'llm:ping {prompt=Say hi in one word.}';

    protected $description = 'Send a short prompt to the Ollama chat model';

    public function handle(ChatProvider $chat): int
    {
        $this->line($chat->complete((string) $this->argument('prompt')));

        return self::SUCCESS;
    }
}
