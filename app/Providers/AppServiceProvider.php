<?php

namespace App\Providers;

use App\Llm\ChatProvider;
use App\Llm\DeepSeekChatProvider;
use App\Models\CopilotRequest;
use App\Models\Scene;
use App\Policies\CopilotPolicy;
use App\Rag\EmbeddingProvider;
use App\Rag\OllamaEmbeddingProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ChatProvider::class, function ($app) {
            return match (config('llm.driver')) {
                'deepseek' => $app->make(DeepSeekChatProvider::class),
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
        Gate::policy(Scene::class, CopilotPolicy::class);
        Gate::policy(CopilotRequest::class, CopilotPolicy::class);
    }
}
