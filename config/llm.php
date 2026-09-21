<?php

return [
    /*
    | Which chat provider to use for Copilot and extractor.
    | Options: 'ollama', 'deepseek'
    */
    'driver' => env('LLM_DRIVER', 'ollama'),

    /*
    | Request JSON-formatted responses when the provider supports it.
    */
    'json_mode' => filter_var(env('LLM_JSON_MODE', true), FILTER_VALIDATE_BOOLEAN),

    /*
    | Default output and temperature. Per-request options override these.
    */
    'max_output_tokens' => (int) env('LLM_MAX_OUTPUT_TOKENS', 3000),
    'temperature' => (float) env('LLM_TEMPERATURE', 0.7),

    'deepseek' => [
        'api_key' => env('DEEPSEEK_API_KEY'),
        'base_url' => env('DEEPSEEK_BASE_URL', 'https://api.deepseek.com/v1'),
        'chat_model' => env('DEEPSEEK_CHAT_MODEL', 'deepseek-chat'),
        'timeout' => (int) env('DEEPSEEK_TIMEOUT', 120),
    ],
];