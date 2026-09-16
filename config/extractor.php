<?php

return [
    'driver' => env('EXTRACTOR_DRIVER', 'ollama'),
    'ollama_model' => env('EXTRACTOR_OLLAMA_MODEL', env('OLLAMA_CHAT_MODEL', 'qwen3:8b')),
    'temperature' => (float) env('EXTRACTOR_TEMPERATURE', 0.1),
    'http_timeout_seconds' => (int) env('EXTRACTOR_HTTP_TIMEOUT_SECONDS', 300),
    'think' => filter_var(env('EXTRACTOR_THINK', false), FILTER_VALIDATE_BOOL),
    'characters_per_token' => (int) env('EXTRACTOR_CHARACTERS_PER_TOKEN', 2),
    'context_reserve_tokens' => 256,
    'min_output_tokens' => 512,
    'prompt_version' => 'graph-extract-v2',
    'catalog_max_entities' => (int) env('EXTRACTOR_CATALOG_MAX_ENTITIES', 150),

    'profiles' => [
        'lore' => [
            'article_max_chars' => (int) env('EXTRACTOR_LORE_ARTICLE_MAX_CHARS', 10000),
            'system_tokens' => (int) env('EXTRACTOR_LORE_SYSTEM_TOKENS', 3000),
            'catalog_tokens' => (int) env('EXTRACTOR_LORE_CATALOG_TOKENS', 1000),
            'output_tokens' => (int) env('EXTRACTOR_LORE_OUTPUT_TOKENS', 7128),
        ],
        'scene' => [
            'system_tokens' => (int) env('EXTRACTOR_SCENE_SYSTEM_TOKENS', 1800),
            'catalog_tokens' => (int) env('EXTRACTOR_SCENE_CATALOG_TOKENS', 1000),
            'feed_tokens' => (int) env('EXTRACTOR_SCENE_FEED_TOKENS', 5392),
            'input_tokens' => (int) env('EXTRACTOR_SCENE_INPUT_TOKENS', 8192),
            'output_tokens' => (int) env('EXTRACTOR_SCENE_OUTPUT_TOKENS', 7936),
            'message_limit' => (int) env('EXTRACTOR_SCENE_MESSAGE_LIMIT', 30),
        ],
        'biography' => [
            'output_tokens' => (int) env('EXTRACTOR_BIOGRAPHY_OUTPUT_TOKENS', 7128),
        ],
    ],
];
