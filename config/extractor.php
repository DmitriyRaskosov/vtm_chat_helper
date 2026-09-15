<?php

return [
    'driver' => env('EXTRACTOR_DRIVER', 'ollama'),
    'ollama_model' => env('EXTRACTOR_OLLAMA_MODEL', env('OLLAMA_CHAT_MODEL', 'qwen3:8b')),
    'temperature' => (float) env('EXTRACTOR_TEMPERATURE', 0.1),
    'max_output_tokens' => (int) env('EXTRACTOR_MAX_OUTPUT_TOKENS', 5000),
    'http_timeout_seconds' => (int) env('EXTRACTOR_HTTP_TIMEOUT_SECONDS', 300),
    'think' => filter_var(env('EXTRACTOR_THINK', false), FILTER_VALIDATE_BOOL),
    'scene_message_limit' => (int) env('EXTRACTOR_SCENE_MESSAGE_LIMIT', 30),
    'catalog_max_entities' => (int) env('EXTRACTOR_CATALOG_MAX_ENTITIES', 150),
    'prompt_version' => 'graph-extract-v1',
];
