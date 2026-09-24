<?php

namespace App\Providers;

use App\Llm\ChatProvider;
use App\Llm\DeepseekChatProvider;
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
    }

    public function boot(): void
    {
        //
    }
}
