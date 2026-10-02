<?php

return [
    'driver' => env('EMBEDDING_DRIVER', 'ollama'),

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://host.docker.internal:11434'),
        'model' => env('EMBEDDING_MODEL', 'qwen3-embedding:0.6b'),
        'dimensions' => (int) env('EMBEDDING_DIMENSIONS', 1024),
        'timeout' => (int) env('EMBEDDING_TIMEOUT', 60),
    ],
];