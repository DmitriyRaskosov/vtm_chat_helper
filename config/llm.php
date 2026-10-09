<?php

return [
    /*
    | Chat provider for Copilot and diary LLM text (cloud API).
    */
    'driver' => env('LLM_DRIVER', 'deepseek'),

    /*
    | Request JSON-formatted responses when the provider supports it.
    */
    'json_mode' => filter_var(env('LLM_JSON_MODE', false), FILTER_VALIDATE_BOOLEAN),

    /*
    | Model context window (input + reserved output) for ContextAssembler budget checks.
    */
    'context_length' => (int) env('LLM_CONTEXT_LENGTH', 16384),

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