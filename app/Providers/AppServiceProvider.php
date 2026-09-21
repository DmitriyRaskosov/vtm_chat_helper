<?php

namespace App\Providers;

use App\Llm\ChatProvider;
use App\Llm\ExtractorChatProvider;
use App\Llm\DeepseekChatProvider;
use App\Llm\OllamaChatProvider;
use App\Rag\EmbeddingProvider;
use App\Rag\OllamaEmbeddingProvider;
use App\Rag\StubEmbeddingProvider;
use App\Retrieval\Tools\GetMessageRangeTool;
use App\Retrieval\Tools\RetrievalToolRegistry;
use App\Retrieval\Tools\SearchMessagesTool;
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

        $this->app->singleton(ChatProvider::class, function ($app) {
            return match (config('llm.driver')) {
                'deepseek' => $app->make(DeepseekChatProvider::class),
                'ollama' => $app->make(OllamaChatProvider::class),
                default => throw new \RuntimeException('Unknown LLM driver: '.config('llm.driver')),
            };
        });
        $this->app->singleton(ExtractorChatProvider::class);

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
