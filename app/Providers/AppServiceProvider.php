<?php

namespace App\Providers;

use App\Llm\ChatProvider;
use App\Llm\DeepseekChatProvider;
use App\Rag\EmbeddingProvider;
use App\Rag\OllamaEmbeddingProvider;
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

        $this->app->singleton(EmbeddingProvider::class, function () {
            return match (config('embedding.driver')) {
                'ollama' => $this->app->make(OllamaEmbeddingProvider::class),
                default => throw new \RuntimeException('Unknown embedding driver: '.config('embedding.driver')),
            };
        });
    }

    public function boot(): void
    {
        //
    }
}
