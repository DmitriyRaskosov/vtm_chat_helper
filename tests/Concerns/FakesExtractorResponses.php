<?php

namespace Tests\Concerns;

use App\Enums\LoreEntryStatus;
use App\Enums\LoreVisibility;
use App\Lore\LoreEntryService;
use App\Models\Chronicle;
use App\Models\LoreEntry;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

trait FakesExtractorResponses
{
    /**
     * @param  array<string, mixed>  $payload
     */
    protected function fakeOllamaExtraction(array $payload): void
    {
        Http::fake([
            config('ollama.url').'/api/chat' => function (Request $request) use ($payload) {
                return Http::response([
                    'message' => [
                        'content' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                    ],
                ]);
            },
        ]);
    }

    protected function createLoreEntry(Chronicle $chronicle, string $text): LoreEntry
    {
        return $this->app->make(LoreEntryService::class)->publish(
            $chronicle,
            [
                'title' => 'Тестовая статья',
                'canonical_text' => $text,
                'status' => LoreEntryStatus::Approved,
                'visibility' => LoreVisibility::Public,
            ],
            'Fixture for extractor tests.',
        );
    }
}
