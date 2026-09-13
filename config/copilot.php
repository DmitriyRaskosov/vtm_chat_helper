<?php

return [
    'history_limit' => (int) env('COPILOT_HISTORY_LIMIT', 30),
    'topic_history_limit' => (int) env('COPILOT_TOPIC_HISTORY_LIMIT', 8),
    'topic_max_output_tokens' => (int) env('COPILOT_TOPIC_MAX_OUTPUT_TOKENS', 384),
    'topic_temperature' => (float) env('COPILOT_TOPIC_TEMPERATURE', 0.2),
    'topic_max_count' => 8,
    'draft_count' => (int) env('COPILOT_DRAFT_COUNT', 3),
    'tools' => [
        'enabled' => filter_var(env('COPILOT_TOOLS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'max_iterations' => (int) env('COPILOT_TOOLS_MAX_ITERATIONS', 2),
        'search_limit' => (int) env('COPILOT_TOOLS_SEARCH_LIMIT', 5),
        'range_limit' => (int) env('COPILOT_TOOLS_RANGE_LIMIT', 20),
        'max_item_characters' => (int) env('COPILOT_TOOLS_MAX_ITEM_CHARACTERS', 400),
        'max_result_tokens' => (int) env('COPILOT_TOOLS_MAX_RESULT_TOKENS', 800),
        'max_loop_tokens' => (int) env('COPILOT_TOOLS_MAX_LOOP_TOKENS', 2000),
    ],
];
