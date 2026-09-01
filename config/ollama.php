<?php

return [
    'url' => env('OLLAMA_URL', 'http://ollama:11434'),
    'embed_model' => env('RAG_EMBEDDING_MODEL', 'qwen3-embedding:0.6b'),
    'chat_model' => env('OLLAMA_CHAT_MODEL', 'qwen3:8b'),
];
