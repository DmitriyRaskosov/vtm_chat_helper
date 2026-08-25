<?php

return [
    'dimensions' => (int) env('RAG_EMBEDDING_DIMENSIONS', 768),
    'driver' => env('RAG_EMBEDDING_DRIVER', 'ollama'),
    'index_sync' => filter_var(env('RAG_INDEX_SYNC', true), FILTER_VALIDATE_BOOLEAN),
    'ollama_url' => env('OLLAMA_URL', 'http://ollama:11434'),
    'model' => env('RAG_EMBEDDING_MODEL', 'nomic-embed-text'),
];
