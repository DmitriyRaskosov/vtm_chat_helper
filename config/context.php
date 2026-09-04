<?php

return [
    'token_estimator' => [
        'characters_per_token' => (int) env('CONTEXT_CHARACTERS_PER_TOKEN', 3),
    ],

    'l0' => [
        'max_tokens' => (int) env('CONTEXT_L0_MAX_TOKENS', 15000),
        'max_messages' => (int) env('CONTEXT_L0_MAX_MESSAGES', 50),
    ],

    'l1' => [
        'summary_count' => (int) env('CONTEXT_L1_SUMMARY_COUNT', 5),
    ],

    'summaries' => [
        'rag_limit' => (int) env('CONTEXT_SUMMARY_RAG_LIMIT', 5),
        'context_length' => (int) env('CONTEXT_SUMMARY_CONTEXT_LENGTH', 24576),
        'max_output_tokens' => (int) env('CONTEXT_SUMMARY_MAX_OUTPUT_TOKENS', 3000),
    ],

    'copilot' => [
        'max_input_tokens' => (int) env('CONTEXT_COPILOT_MAX_INPUT_TOKENS', 12000),
    ],

    'intent' => [
        'request_limit' => (int) env('CONTEXT_INTENT_REQUEST_LIMIT', 20),
        'context_length' => (int) env('CONTEXT_INTENT_CONTEXT_LENGTH', 8192),
        'max_output_tokens' => (int) env('CONTEXT_INTENT_MAX_OUTPUT_TOKENS', 400),
    ],
];
