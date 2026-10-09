<?php

return [
    'driver' => env('EMBEDDING_DRIVER', 'ollama'),

    'ollama' => [
        'url' => env('OLLAMA_URL', 'http://host.docker.internal:11434'),
        'model' => env('EMBEDDING_MODEL', 'bge-m3'),
        'dimensions' => (int) env('EMBEDDING_DIMENSIONS', 1024),
        'timeout' => (int) env('EMBEDDING_TIMEOUT', 60),
    ],
];