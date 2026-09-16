<?php

return [
    'url' => env('OLLAMA_URL', 'http://ollama:11434'),
    'embed_model' => env('RAG_EMBEDDING_MODEL', 'qwen3-embedding:0.6b'),
    'chat_model' => env('OLLAMA_CHAT_MODEL', 'qwen3:8b'),
    'context_length' => (int) env('OLLAMA_CONTEXT_LENGTH', 16384),
    'max_output_tokens' => (int) env('OLLAMA_MAX_OUTPUT_TOKENS', 3000),
    'chat_format' => env('OLLAMA_CHAT_FORMAT', 'json'),
    'chat_temperature' => (float) env('OLLAMA_CHAT_TEMPERATURE', 0.1),
];
