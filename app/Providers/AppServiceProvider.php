<?php

namespace App\Providers;

use App\Llm\ChatProvider;
use App\Llm\DeepseekChatProvider;
use App\Retrieval\Tools\GetMessageRangeTool;
use App\Retrieval\Tools\RetrievalToolRegistry;
use App\Retrieval\Tools\SearchMessagesTool;
use Illuminate\Support\ServiceProvider;


class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatProvider::class, function ($app) {
            return match (config('llm.driver')) {
                'deepseek' => $app->make(DeepseekChatProvider::class),
                default => throw new \RuntimeException('Unknown LLM driver: '.config('llm.driver')),
            };
        });

        $this->app->singleton(RetrievalToolRegistry::class, function ($app) {
            return new RetrievalToolRegistry([
                $app->make(SearchMessagesTool::class),
                $app->make(GetMessageRangeTool::class),
            ]);
        });
    }

    public function boot(): void
    {
        //
    }
}
