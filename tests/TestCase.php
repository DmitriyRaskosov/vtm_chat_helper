<?php

namespace Tests;

use App\Llm\ChatProvider;
use App\Rag\EmbeddingProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\FakeChatProvider;
use Tests\Support\FakeEmbeddingProvider;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function fakeChat(?FakeChatProvider $fake = null): FakeChatProvider
    {
        $fake ??= new FakeChatProvider;
        $this->app->instance(ChatProvider::class, $fake);

        return $fake;
    }

    protected function fakeEmbeddings(?FakeEmbeddingProvider $fake = null): FakeEmbeddingProvider
    {
        $fake ??= new FakeEmbeddingProvider;
        $this->app->instance(EmbeddingProvider::class, $fake);

        return $fake;
    }
}
