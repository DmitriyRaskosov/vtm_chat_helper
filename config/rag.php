<?php

return [
    'dimensions' => (int) env('RAG_EMBEDDING_DIMENSIONS', 1024),
    'driver' => env('RAG_EMBEDDING_DRIVER', 'ollama'),
    'index_sync' => filter_var(env('RAG_INDEX_SYNC', true), FILTER_VALIDATE_BOOLEAN),
    'ollama_url' => env('OLLAMA_URL', 'http://ollama:11434'),
    'model' => env('RAG_EMBEDDING_MODEL', 'qwen3-embedding:0.6b'),
];
