<?php

return [
    'url' => env('OLLAMA_URL', 'http://ollama:11434'),
    'embed_model' => env('RAG_EMBEDDING_MODEL', 'nomic-embed-text'),
    'chat_model' => env('OLLAMA_CHAT_MODEL', 'qwen3:8b'),
];
