<?php

namespace App\Providers;

use App\Llm\ChatProvider;
use App\Llm\OllamaChatProvider;
use App\Rag\EmbeddingProvider;
use App\Rag\OllamaEmbeddingProvider;
use App\Rag\StubEmbeddingProvider;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmbeddingProvider::class, function () {
            return match (config('rag.driver')) {
                'ollama' => $this->app->make(OllamaEmbeddingProvider::class),
                default => $this->app->make(StubEmbeddingProvider::class),
            };
        });

        $this->app->singleton(ChatProvider::class, OllamaChatProvider::class);
    }

    public function boot(): void
    {
        //
    }
}
